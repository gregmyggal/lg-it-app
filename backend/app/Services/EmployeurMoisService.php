<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Employeur;
use App\Models\EmployeurMoisAudit;
use App\Models\Professeur;
use App\Models\ProfesseurEmployeurMois;
use App\Models\Timesheet;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EMP-01 : employeur d'un animateur, mois par mois.
 *  - Résolution (RG-2) : ligne explicite du mois, sinon valeur du mois précédent défini (remontée), sinon entité par défaut.
 *  - Verrous (RG-7) : fiche PDF générée (personne ne peut modifier, l'admin déverrouille d'abord) ou mois signé
 *    (l'admin peut modifier avec motif, ce qui rend la signature « à re-signer » car l'employeur entre dans l'empreinte).
 *  - Écriture unitaire ou en lot, avec journal (RG-9) et verrou optimiste (RG-15).
 */
class EmployeurMoisService
{
    public const MOIS_A_L_AVANCE = 12;

    public const VERROU_PDF = 'pdf';

    public const VERROU_SIGNATURE = 'signature';

    public const MSG_VERROU_PDF = 'Ce mois est verrouillé : une fiche PDF a été générée. Un administrateur peut le déverrouiller.';

    public const MSG_VERROU_SIGNE = 'Ce mois est signé : l\'employeur ne peut plus être modifié par la direction.';

    public const MSG_VERROU_SIGNE_ADMIN = 'Ce mois est signé par l\'animateur. Modifier l\'employeur invalidera la signature : l\'animateur devra signer à nouveau.';

    // ------------------------------------------------------------------ lecture

    /**
     * Employeur effectif de plusieurs animateurs pour un mois (2 requêtes, pas de N+1).
     *
     * @param  iterable<int>  $professeurIds
     * @return array<int, array{employeur: Employeur, source: string, version: int, herite_de: ?string}>
     */
    public function resoudre(iterable $professeurIds, int $annee, int $mois): array
    {
        $ids = collect($professeurIds)->unique()->values();
        $lignes = ProfesseurEmployeurMois::whereIn('professeur_id', $ids)
            ->whereRaw('annee * 100 + mois <= ?', [$annee * 100 + $mois])
            ->orderByRaw('annee * 100 + mois desc')
            ->get()->groupBy('professeur_id');
        $employeurs = Employeur::all()->keyBy('id');
        $defaut = Employeur::parDefaut();

        $res = [];
        foreach ($ids as $id) {
            $derniere = $lignes->get($id)?->first();
            if ($derniere === null) {
                $res[$id] = ['employeur' => $defaut, 'source' => 'defaut', 'version' => 0, 'herite_de' => null];
            } elseif ($derniere->annee === $annee && $derniere->mois === $mois) {
                $res[$id] = ['employeur' => $employeurs[$derniere->employeur_id], 'source' => $derniere->source, 'version' => $derniere->version, 'herite_de' => null];
            } else {
                $res[$id] = ['employeur' => $employeurs[$derniere->employeur_id], 'source' => 'herite', 'version' => 0, 'herite_de' => sprintf('%04d-%02d', $derniere->annee, $derniere->mois)];
            }
        }

        return $res;
    }

    public function effectif(Professeur $prof, int $annee, int $mois): Employeur
    {
        return $this->resoudre([$prof->id], $annee, $mois)[$prof->id]['employeur'];
    }

    /**
     * Verrous du mois pour plusieurs animateurs (2 requêtes). Absent de la table = modifiable.
     *
     * @param  iterable<int>  $professeurIds
     * @return array<int, array{type: string}>
     */
    public function verrous(iterable $professeurIds, int $annee, int $mois): array
    {
        $ids = collect($professeurIds)->unique()->values();
        $debut = Carbon::create($annee, $mois, 1);
        $fin = $debut->copy()->endOfMonth();

        $verrous = [];
        foreach (TimesheetSignature::whereIn('professeur_id', $ids)->where(['annee' => $annee, 'mois' => $mois, 'statut' => TimesheetSignature::STATUT_VALIDE])->pluck('professeur_id') as $id) {
            $verrous[$id] = ['type' => self::VERROU_SIGNATURE];
        }
        // Un PDF « actif » = saisies au statut généré (le déverrouillage admin les repasse en confirmé : le mois se rouvre, RG-8).
        $generes = Timesheet::whereIn('professeur_id', $ids)->whereBetween('date_prestation', [$debut->toDateString(), $fin->toDateString()])
            ->where('statut_validation', Timesheet::STATUT_GENERE)->distinct()->pluck('professeur_id');
        foreach ($generes as $id) {
            $verrous[$id] = ['type' => self::VERROU_PDF]; // le PDF l'emporte sur la signature
        }

        return $verrous;
    }

    /** Message de verrou adapté au lecteur (l'admin peut modifier un mois signé, pas un mois avec PDF). */
    public function raisonVerrou(?array $verrou, User $lecteur): ?string
    {
        return match ($verrou['type'] ?? null) {
            self::VERROU_PDF => self::MSG_VERROU_PDF,
            self::VERROU_SIGNATURE => $lecteur->isAdmin() ? self::MSG_VERROU_SIGNE_ADMIN : self::MSG_VERROU_SIGNE,
            default => null,
        };
    }

    public function modifiable(?array $verrou, User $lecteur): bool
    {
        return $lecteur->isStaff() && match ($verrou['type'] ?? null) {
            null => true,
            self::VERROU_SIGNATURE => $lecteur->isAdmin(),
            default => false,
        };
    }

    /** Vue d'un mois pour l'écran staff : employeur, origine, version (verrou optimiste), verrou et droit de modification. */
    public function vue(array $resolution, ?array $verrou, User $lecteur): array
    {
        return [
            'employeur' => $resolution['employeur']->resume(),
            'source' => $resolution['source'],
            'herite_de' => $resolution['herite_de'],
            'version' => $resolution['version'],
            'verrouille' => $verrou !== null,
            'type_verrou' => $verrou['type'] ?? null,
            'raison_verrou' => $this->raisonVerrou($verrou, $lecteur),
            'modifiable' => $this->modifiable($verrou, $lecteur),
        ];
    }

    public function pourProfesseur(Professeur $prof, int $annee, int $mois, User $lecteur): array
    {
        return $this->vue($this->resoudre([$prof->id], $annee, $mois)[$prof->id], $this->verrous([$prof->id], $annee, $mois)[$prof->id] ?? null, $lecteur);
    }

    /** Les 12 mois d'une année civile (frise). Le staff voit tout ; le professeur, via le contrôleur, le nom seulement. */
    public function mois(Professeur $prof, int $annee, User $lecteur): array
    {
        $lignes = ProfesseurEmployeurMois::where('professeur_id', $prof->id)->where('annee', '<=', $annee)
            ->orderBy('annee')->orderBy('mois')->get();
        $employeurs = Employeur::all()->keyBy('id');
        $defaut = Employeur::parDefaut();

        $debut = Carbon::create($annee, 1, 1);
        $generes = Timesheet::where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut->toDateString(), $debut->copy()->endOfYear()->toDateString()])
            ->where('statut_validation', Timesheet::STATUT_GENERE)->pluck('date_prestation')->map(fn ($d) => (int) $d->format('n'))->unique()->flip();
        $signes = TimesheetSignature::where(['professeur_id' => $prof->id, 'annee' => $annee, 'statut' => TimesheetSignature::STATUT_VALIDE])->pluck('mois')->flip();

        $sortie = [];
        for ($m = 1; $m <= 12; $m++) {
            $propre = $lignes->first(fn ($l) => $l->annee === $annee && $l->mois === $m);
            $anterieure = $propre ?? $lignes->filter(fn ($l) => $l->annee * 100 + $l->mois < $annee * 100 + $m)->last();
            $res = match (true) {
                $propre !== null => ['employeur' => $employeurs[$propre->employeur_id], 'source' => $propre->source, 'version' => $propre->version, 'herite_de' => null],
                $anterieure !== null => ['employeur' => $employeurs[$anterieure->employeur_id], 'source' => 'herite', 'version' => 0, 'herite_de' => sprintf('%04d-%02d', $anterieure->annee, $anterieure->mois)],
                default => ['employeur' => $defaut, 'source' => 'defaut', 'version' => 0, 'herite_de' => null],
            };
            $verrou = $generes->has($m) ? ['type' => self::VERROU_PDF] : ($signes->has($m) ? ['type' => self::VERROU_SIGNATURE] : null);
            $sortie[] = ['mois' => $m] + $this->vue($res, $verrou, $lecteur);
        }

        return $sortie;
    }

    /** Journal des changements (plus récent d'abord). */
    public function historique(Professeur $prof, ?int $annee = null): Collection
    {
        return EmployeurMoisAudit::with('avant:id,nom', 'apres:id,nom', 'auteur:id,name')
            ->where('professeur_id', $prof->id)
            ->when($annee, fn ($q) => $q->where('annee', $annee))
            ->latest('id')->get();
    }

    // ------------------------------------------------------------------ écriture

    /**
     * Fixe l'employeur d'un mois. `$version` : version lue par l'écran (0 = aucune ligne explicite), null = pas de contrôle (lot).
     *
     * @throws RegleMetierException 422 (période, entité inactive, verrou, motif) ou 409 (modification concurrente)
     */
    public function definir(Professeur $prof, int $annee, int $mois, Employeur $employeur, ?string $motif, ?int $version, User $auteur): ProfesseurEmployeurMois
    {
        $this->verifierPeriode($annee, $mois);
        if (! $employeur->actif) {
            throw RegleMetierException::invalide('Cette entité est désactivée : elle ne peut plus être choisie.', ['employeur_id' => ['Entité désactivée.']]);
        }

        return DB::transaction(function () use ($prof, $annee, $mois, $employeur, $motif, $version, $auteur) {
            $ligne = ProfesseurEmployeurMois::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->lockForUpdate()->first();
            if ($version !== null && ($ligne?->version ?? 0) !== $version) {
                $qui = $ligne?->auteur?->name ?? 'Quelqu\'un';
                $quand = $ligne?->updated_at?->copy()->timezone('Europe/Brussels')->format('H:i');
                throw RegleMetierException::conflit("Modification simultanée : {$qui} a changé cet employeur".($quand ? " à {$quand}" : '').'. Rechargez avant de réessayer.');
            }

            $verrou = $this->verrous([$prof->id], $annee, $mois)[$prof->id] ?? null;
            $this->exigerModifiable($verrou, $auteur);
            if ($ligne && $ligne->employeur_id === $employeur->id) {
                return $ligne; // inchangé : ni écriture ni journal
            }
            $motif = filled($motif) ? trim($motif) : null;
            if ($this->motifRequis($ligne, $annee, $mois, $verrou) && ($motif === null || mb_strlen($motif) < 3)) {
                throw RegleMetierException::invalide('Motif obligatoire pour modifier un employeur déjà défini.', ['motif' => ['Motif obligatoire pour modifier un employeur déjà défini.']]);
            }

            $avant = $this->resoudre([$prof->id], $annee, $mois)[$prof->id]['employeur'];
            if ($ligne) {
                $ligne->update(['employeur_id' => $employeur->id, 'source' => ProfesseurEmployeurMois::SOURCE_EXPLICITE, 'version' => $ligne->version + 1, 'updated_by' => $auteur->id]);
            } else {
                $ligne = ProfesseurEmployeurMois::create([
                    'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'employeur_id' => $employeur->id,
                    'source' => ProfesseurEmployeurMois::SOURCE_EXPLICITE, 'version' => 1, 'updated_by' => $auteur->id,
                ]);
            }
            $this->journaliser($prof, $annee, $mois, $avant->id, $employeur->id, $motif, $auteur->id);

            return $ligne->refresh();
        });
    }

    /**
     * Fixe l'employeur de plusieurs animateurs pour un mois : soit une entité, soit « reprendre le mois précédent ».
     * Les mois verrouillés sont ignorés (listés avec la raison), les autres appliqués.
     *
     * @param  int[]  $professeurIds
     * @return array{appliques: list<array>, ignores: list<array>}
     */
    public function lot(array $professeurIds, int $annee, int $mois, ?Employeur $employeur, bool $reprendrePrecedent, ?string $motif, User $auteur): array
    {
        $this->verifierPeriode($annee, $mois);
        if (! $reprendrePrecedent && ($employeur === null || ! $employeur->actif)) {
            throw RegleMetierException::invalide('Choisissez une entité active.', ['employeur_id' => ['Entité manquante ou désactivée.']]);
        }

        $profs = Professeur::whereIn('id', $professeurIds)->get()->keyBy('id');
        $verrous = $this->verrous($profs->keys(), $annee, $mois);
        $lignes = ProfesseurEmployeurMois::whereIn('professeur_id', $profs->keys())->where(['annee' => $annee, 'mois' => $mois])->get()->keyBy('professeur_id');
        $precedent = $reprendrePrecedent ? $this->resoudre($profs->keys(), ...$this->moisPrecedent($annee, $mois)) : [];

        $ignores = [];
        $cibles = [];
        foreach ($profs as $id => $prof) {
            $v = $verrous[$id] ?? null;
            if (! $this->modifiable($v, $auteur)) {
                $ignores[] = ['professeur_id' => $id, 'professeur' => $this->nom($prof), 'raison' => ($v['type'] ?? null) === self::VERROU_PDF ? 'Mois verrouillé (fiche PDF générée)' : 'Mois signé par l\'animateur'];

                continue;
            }
            $cible = $reprendrePrecedent ? $precedent[$id]['employeur'] : $employeur;
            if (! $cible->actif) {
                $ignores[] = ['professeur_id' => $id, 'professeur' => $this->nom($prof), 'raison' => 'Entité « '.$cible->nom.' » désactivée'];

                continue;
            }
            $cibles[$id] = $cible;
        }

        $motif = filled($motif) ? trim($motif) : null;
        $besoinMotif = false;
        foreach ($cibles as $id => $cible) {
            $l = $lignes->get($id);
            if (! ($l && $l->employeur_id === $cible->id) && $this->motifRequis($l, $annee, $mois, $verrous[$id] ?? null)) {
                $besoinMotif = true;
                break;
            }
        }
        if ($besoinMotif && ($motif === null || mb_strlen($motif) < 3)) {
            throw RegleMetierException::invalide('Motif obligatoire pour modifier un employeur déjà défini ou un mois passé.', ['motif' => ['Motif obligatoire.']]);
        }

        $appliques = [];
        DB::transaction(function () use ($cibles, $profs, $annee, $mois, $motif, $auteur, &$appliques, &$ignores) {
            foreach ($cibles as $id => $cible) {
                try {
                    $this->definir($profs[$id], $annee, $mois, $cible, $motif, null, $auteur);
                    $appliques[] = ['professeur_id' => $id, 'professeur' => $this->nom($profs[$id])];
                } catch (RegleMetierException $e) {
                    $ignores[] = ['professeur_id' => $id, 'professeur' => $this->nom($profs[$id]), 'raison' => $e->getMessage()];
                }
            }
        });

        return ['appliques' => $appliques, 'ignores' => $ignores];
    }

    /**
     * RG-3 : écrit l'employeur effectif du mois (source « fige ») s'il n'est pas encore explicite, avant un acte à valeur
     * légale (génération du PDF, signature). Retourne l'employeur du mois.
     */
    public function materialiser(Professeur $prof, int $annee, int $mois, ?User $auteur, string $motif): Employeur
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $auteur, $motif) {
            $ligne = ProfesseurEmployeurMois::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->lockForUpdate()->first();
            if ($ligne) {
                return Employeur::findOrFail($ligne->employeur_id);
            }
            $employeur = $this->effectif($prof, $annee, $mois);
            try {
                ProfesseurEmployeurMois::create([
                    'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'employeur_id' => $employeur->id,
                    'source' => ProfesseurEmployeurMois::SOURCE_FIGE, 'version' => 1, 'updated_by' => $auteur?->id,
                ]);
                $this->journaliser($prof, $annee, $mois, null, $employeur->id, $motif, $auteur?->id);
            } catch (QueryException) {
                // créé en parallèle par un autre acte : on relit
                return Employeur::findOrFail(ProfesseurEmployeurMois::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->value('employeur_id'));
            }

            return $employeur;
        });
    }

    // ------------------------------------------------------------------ règles internes

    /** @return array{0: int, 1: int} [année, mois] du mois précédent */
    private function moisPrecedent(int $annee, int $mois): array
    {
        return $mois === 1 ? [$annee - 1, 12] : [$annee, $mois - 1];
    }

    /** RG-11 : de janvier 2020 jusqu'à 12 mois à l'avance. */
    private function verifierPeriode(int $annee, int $mois): void
    {
        $limite = Carbon::now('Europe/Brussels')->startOfMonth()->addMonths(self::MOIS_A_L_AVANCE);
        if ($annee < 2020 || $annee * 100 + $mois > $limite->year * 100 + $limite->month) {
            throw RegleMetierException::invalide('L\'employeur ne peut être défini que jusqu\'à '.self::MOIS_A_L_AVANCE.' mois à l\'avance.', ['mois' => ['Mois hors plage.']]);
        }
    }

    private function exigerModifiable(?array $verrou, User $auteur): void
    {
        if ($verrou === null) {
            return;
        }
        if ($verrou['type'] === self::VERROU_SIGNATURE && $auteur->isAdmin()) {
            return;
        }
        throw RegleMetierException::invalide($this->raisonVerrou($verrou, $auteur), ['mois' => [$this->raisonVerrou($verrou, $auteur)]]);
    }

    /** RG-9 : motif pour modifier une valeur explicite, pour un mois passé, et pour toute modification d'un mois signé. */
    private function motifRequis(?ProfesseurEmployeurMois $ligne, int $annee, int $mois, ?array $verrou): bool
    {
        $courant = Carbon::now('Europe/Brussels');

        return $ligne !== null
            || $annee * 100 + $mois < $courant->year * 100 + $courant->month
            || ($verrou['type'] ?? null) === self::VERROU_SIGNATURE;
    }

    private function journaliser(Professeur $prof, int $annee, int $mois, ?int $avantId, int $apresId, ?string $motif, ?int $userId): void
    {
        EmployeurMoisAudit::create([
            'professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois, 'employeur_avant_id' => $avantId,
            'employeur_apres_id' => $apresId, 'motif' => $motif, 'user_id' => $userId,
        ]);
    }

    private function nom(Professeur $p): string
    {
        return trim($p->prenom.' '.$p->nom);
    }
}
