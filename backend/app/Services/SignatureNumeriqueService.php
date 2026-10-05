<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\ParametreSite;
use App\Models\Professeur;
use App\Models\SignatureSpecimen;
use App\Models\Timesheet;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * SIG-01 : signature électronique simple du mois par le professeur.
 * Preuve = contenu signé (lignes, montants, IBAN) haché en SHA-256 + signataire + horodatage, IP et appareil relevés
 * par le serveur + empreinte de l'image, le tout scellé en Ed25519. Le PDF n'utilise une signature que si le contenu
 * actuel correspond encore à l'empreinte signée.
 */
class SignatureNumeriqueService
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // base32 Crockford

    public function __construct(
        private readonly FicheDefraiementLignes $fiche,
        private readonly SignatureCle $cle,
    ) {}

    /** Signe le mois : seul le professeur concerné, avec sa signature enregistrée et la certification cochée. */
    public function signer(Professeur $prof, int $annee, int $mois, User $signataire, ?string $ip, ?string $userAgent): TimesheetSignature
    {
        if (! $signataire->isProfesseur() || $signataire->professeur?->id !== $prof->id) {
            throw new RegleMetierException('Seul le professeur concerné peut signer son mois.', 403);
        }
        $specimen = SignatureSpecimen::where('user_id', $signataire->id)->first();
        if (! $specimen || ! Storage::disk('local')->exists($specimen->chemin)) {
            throw RegleMetierException::invalide('Créez d\'abord votre signature.', ['signature' => ['Signature manquante.']]);
        }

        return DB::transaction(function () use ($prof, $annee, $mois, $signataire, $ip, $userAgent, $specimen) {
            $this->fiche->saisies($prof, $annee, $mois, verrou: true);
            $etat = (new TimesheetSignatureService)->canSignMonth($prof->id, $annee, $mois);
            if (! $etat['can_sign']) {
                throw RegleMetierException::invalide($etat['errors'][0] ?? 'Impossible de signer ce mois.');
            }

            $debut = Carbon::create($annee, $mois, 1);
            Timesheet::where('professeur_id', $prof->id)
                ->whereBetween('date_prestation', [$debut->toDateString(), $debut->copy()->endOfMonth()->toDateString()])
                ->where('statut_validation', Timesheet::STATUT_CONFIRME)
                ->whereNull('signature_professeur')
                ->update(['signature_professeur' => now()]);

            TimesheetSignature::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'statut' => TimesheetSignature::STATUT_VALIDE])
                ->update(['statut' => TimesheetSignature::STATUT_REMPLACEE]);

            $publicId = $this->nouvelId();
            $signeA = now()->utc()->startOfSecond();
            $hash = $this->empreinte($prof, $annee, $mois);
            $verification = ParametreSite::booleen(ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE);
            $payload = $this->json([
                'version' => 1,
                'id' => $publicId,
                'contenu_sha256' => $hash,
                'professeur_id' => $prof->id,
                'annee' => $annee,
                'mois' => $mois,
                'signataire' => ['user_id' => $signataire->id, 'nom' => trim($prof->prenom.' '.$prof->nom)],
                'signe_a' => $signeA->toIso8601ZuluString(),
                'ip' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'specimen' => ['type' => $specimen->type, 'sha256' => $specimen->sha256],
                'verification_publique' => $verification,
                'cle' => $this->cle->id(),
            ]);

            // Copie figée : changer de signature plus tard ne modifie jamais une fiche déjà signée.
            $copie = "signatures/mois/{$publicId}.png";
            Storage::disk('local')->copy($specimen->chemin, $copie);

            return TimesheetSignature::create([
                'public_id' => $publicId, 'professeur_id' => $prof->id, 'user_id' => $signataire->id,
                'annee' => $annee, 'mois' => $mois, 'statut' => TimesheetSignature::STATUT_VALIDE,
                'signed_at' => $signeA, 'ip' => $ip, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'payload' => $payload, 'content_hash' => $hash, 'seal' => $this->cle->sceller($payload), 'key_id' => $this->cle->id(),
                'specimen_type' => $specimen->type, 'specimen_chemin' => $copie, 'verification_publique' => $verification,
            ]);
        });
    }

    /** Dernière signature en vigueur du mois (les précédentes sont « remplacées »). */
    public function derniere(Professeur $prof, int $annee, int $mois): ?TimesheetSignature
    {
        return TimesheetSignature::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'statut' => TimesheetSignature::STATUT_VALIDE])
            ->latest('id')->first();
    }

    /** Le contenu actuel du mois (lignes, montants, IBAN) diffère-t-il de ce qui a été signé ? */
    public function estPerimee(TimesheetSignature $sig): bool
    {
        return ! hash_equals($sig->content_hash, $this->empreinte($sig->professeur, $sig->annee, $sig->mois));
    }

    /** Le mois a une signature en vigueur dont le contenu a changé : une nouvelle signature est nécessaire. */
    public function aResigner(Professeur $prof, int $annee, int $mois): bool
    {
        $sig = $this->derniere($prof, $annee, $mois);

        return $sig !== null && $this->estPerimee($sig);
    }

    /** null = preuve anonymisée (au-delà de la durée de conservation) : le sceau n'est plus vérifiable. */
    public function sceauValide(TimesheetSignature $sig): ?bool
    {
        if ($sig->payload === null || $sig->seal === null) {
            return null;
        }

        return $this->cle->verifier($sig->payload, $sig->seal, $sig->key_id)
            && (json_decode($sig->payload, true)['contenu_sha256'] ?? null) === $sig->content_hash;
    }

    /** Preuve affichée à la direction (IP complète pour l'admin, masquée pour le directeur) ou au professeur. */
    public function preuve(TimesheetSignature $sig, User $lecteur): array
    {
        return [
            'public_id' => $sig->public_id,
            'signed_at' => $sig->signed_at,
            'signataire' => trim($sig->professeur->prenom.' '.$sig->professeur->nom),
            'specimen_type' => $sig->specimen_type,
            'image' => app(SignatureSpecimenService::class)->dataUrl($sig->specimen_chemin),
            'ip' => $sig->ip === null ? null : ($lecteur->isAdmin() ? $sig->ip : self::masquerIp($sig->ip)),
            'appareil' => self::appareil($sig->user_agent),
            'empreinte' => $sig->content_hash,
            'cle' => $sig->key_id,
            'perimee' => $this->estPerimee($sig),
            'sceau_valide' => $this->sceauValide($sig),
            'verification_publique' => $sig->verification_publique,
            'anonymisee' => $sig->anonymisee_at !== null,
        ];
    }

    /**
     * Vérification publique d'un identifiant imprimé sur une fiche. Seulement pour les signatures émises avec la
     * vérification activée ; données minimales (jamais montant, IBAN, IP ni e-mail). null = introuvable.
     */
    public function verificationPublique(string $publicId): ?array
    {
        $sig = TimesheetSignature::with('professeur')->where('public_id', strtoupper($publicId))->first();
        if (! $sig || ! $sig->verification_publique) {
            return null;
        }
        $p = $sig->professeur;
        $initiales = collect([$p->prenom, $p->nom])->filter()->map(fn ($m) => mb_strtoupper(mb_substr(trim($m), 0, 1)).'.')->implode(' ');
        $sceau = $this->sceauValide($sig);

        return [
            'public_id' => $sig->public_id,
            'statut' => match (true) {
                $sceau === false => 'invalide',
                $sig->statut === TimesheetSignature::STATUT_REMPLACEE => 'remplacee',
                $sceau === null => 'archivee',
                default => 'valide',
            },
            'document' => 'Fiche de défraiement – '.Carbon::create($sig->annee, $sig->mois, 1)->locale('fr')->translatedFormat('F Y'),
            'signataire' => $initiales,
            'signed_at' => $sig->signed_at,
            'emetteur' => config('logiscool.association.nom'),
        ];
    }

    /** RGPD : au-delà de la durée de conservation, l'IP, l'appareil et le contenu détaillé sont effacés. */
    public function anonymiser(): int
    {
        return TimesheetSignature::whereNull('anonymisee_at')
            ->where('signed_at', '<', now()->subYears((int) config('signature.retention_annees')))
            ->update(['ip' => null, 'user_agent' => null, 'payload' => null, 'anonymisee_at' => now()]);
    }

    public function empreinte(Professeur $prof, int $annee, int $mois): string
    {
        return hash('sha256', $this->json($this->fiche->contenu($prof, $annee, $mois)));
    }

    public static function masquerIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $o = explode('.', $ip);

            return "{$o[0]}.{$o[1]}.x.x";
        }

        return implode(':', array_slice(explode(':', $ip), 0, 2)).':…';
    }

    /** « Mobile · Safari » : résumé lisible du user-agent. */
    public static function appareil(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        $type = preg_match('/iPad|Tablet/i', $ua) ? 'Tablette' : (preg_match('/Mobile|Android|iPhone/i', $ua) ? 'Mobile' : 'Ordinateur');
        $navigateur = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Autre navigateur',
        };

        return "{$type} · {$navigateur}";
    }

    private function json(array $donnees): string
    {
        return json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** « SIG-7K3F-9QX2 » : 40 bits aléatoires, non séquentiel (pas d'énumération possible). */
    private function nouvelId(): string
    {
        do {
            $c = '';
            for ($i = 0; $i < 8; $i++) {
                $c .= self::ALPHABET[random_int(0, 31)];
            }
            $id = 'SIG-'.substr($c, 0, 4).'-'.substr($c, 4);
        } while (TimesheetSignature::where('public_id', $id)->exists());

        return $id;
    }
}
