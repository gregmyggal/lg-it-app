<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Models\SignatureSpecimen;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TS-01 T4 : reconfirmation par le professeur. Après validation ou ajustement, le professeur signe son mois ou le
 * conteste (motif obligatoire) ; la direction traite la contestation (les saisies repassent en revue).
 * Statut « à reconfirmer » = saisie confirmée sans signature (calculé, ex. « Attente professeur »).
 */
class TimesheetConfirmationService
{
    public function __construct(
        private readonly TimesheetSyntheseMoisService $synthese,
        private readonly FicheDefraiementLignes $fiche,
        private readonly SignatureNumeriqueService $signatures,
    ) {}

    private function lignes(Professeur $prof, int $annee, int $mois)
    {
        $debut = Carbon::create($annee, $mois, 1);

        return Timesheet::where('professeur_id', $prof->id)
            ->whereBetween('date_prestation', [$debut->toDateString(), $debut->copy()->endOfMonth()->toDateString()]);
    }

    public function statutMois(Professeur $prof, int $annee, int $mois): string
    {
        return $this->synthese->statutMois($this->lignes($prof, $annee, $mois)->get());
    }

    /** État pour l'écran du professeur : ajustements de la direction, contestation en cours, signature possible. */
    public function etat(Professeur $prof, int $annee, int $mois): array
    {
        $lignes = $this->lignes($prof, $annee, $mois)->get();
        $audits = TimesheetAudit::with('auteur:id,name', 'timesheet:id,date_prestation,type_activite')
            ->whereIn('timesheet_id', $lignes->pluck('id'))
            ->orderBy('id')->get();

        $ajustements = $audits->whereIn('action', [TimesheetAudit::ACTION_ADAPTATION, TimesheetAudit::ACTION_LISSAGE])
            ->map(fn (TimesheetAudit $a) => [
                'id' => $a->id,
                'date_prestation' => $a->timesheet?->date_prestation?->toDateString(),
                'action' => $a->action,
                'avant' => $a->avant,
                'apres' => $a->apres,
                'motif' => $a->motif,
                'created_at' => $a->created_at,
            ])->values();

        $derniere = $audits->where('action', TimesheetAudit::ACTION_CONTESTATION)->last();
        $reponse = $audits->where('action', TimesheetAudit::ACTION_REPONSE)->last();
        $contestationOuverte = $lignes->contains('statut_validation', Timesheet::STATUT_CONTESTE);

        // TS-02 : lignes encore en brouillon dont la dernière trace est une remise en brouillon par la direction.
        $remise = $audits->filter(fn (TimesheetAudit $a) => $a->action === TimesheetAudit::ACTION_REMISE_BROUILLON
            && $lignes->firstWhere('id', $a->timesheet_id)?->statut_validation === Timesheet::STATUT_BROUILLON
            && $audits->where('timesheet_id', $a->timesheet_id)->last()?->id === $a->id)->last();

        $sign = (new TimesheetSignatureService)->canSignMonth($prof->id, $annee, $mois);

        return [
            'statut_mois' => $this->synthese->statutMois($lignes),
            'peut_signer' => $sign['can_sign'],
            'erreurs_signature' => $sign['errors'],
            'peut_contester' => $lignes->where('statut_validation', Timesheet::STATUT_CONFIRME)->whereNull('signature_professeur')->isNotEmpty(),
            'ajustements' => $ajustements,
            'contestation' => $contestationOuverte && $derniere ? ['motif' => $derniere->motif, 'created_at' => $derniere->created_at] : null,
            'pdf' => ($pdf = \App\Models\TimesheetPdf::where(['professeur_id' => $prof->id, 'annee' => $annee, 'mois' => $mois])->orderByDesc('version')->first())
                ? ['id' => $pdf->id, 'version' => $pdf->version, 'generated_at' => $pdf->generated_at] : null,
            'remise_brouillon' => $remise ? ['motif' => $remise->motif, 'auteur' => $remise->auteur?->name, 'created_at' => $remise->created_at] : null,
            'derniere_reponse' => $reponse ? ['motif' => $reponse->motif, 'created_at' => $reponse->created_at] : null,
            'a_signature' => SignatureSpecimen::where('user_id', $prof->user_id)->exists(),
            'recapitulatif' => $this->recapitulatif($prof, $annee, $mois, $ajustements->count()),
            'signature' => ($sig = $this->signatures->derniere($prof, $annee, $mois)) ? [
                'public_id' => $sig->public_id,
                'signed_at' => $sig->signed_at,
                'perimee' => $this->signatures->estPerimee($sig),
                'image' => app(SignatureSpecimenService::class)->dataUrl($sig->specimen_chemin),
            ] : null,
        ];
    }

    /** SIG-01 : ce que le professeur s'apprête à signer (mêmes lignes que la fiche PDF). */
    private function recapitulatif(Professeur $prof, int $annee, int $mois, int $ajustements): array
    {
        $saisies = $this->fiche->saisies($prof, $annee, $mois);
        $lignes = $this->fiche->lignes($prof, $saisies);
        $iban = preg_replace('/\s+/', '', (string) $prof->compte_bancaire);

        return [
            'lignes' => $lignes->count(),
            'heures' => round((float) $saisies->where('type_activite', '!=', TimesheetService::TYPE_DEPLACEMENT)->sum('nombre_heures'), 2),
            'total_eur' => round((float) $lignes->sum('total'), 2),
            'ajustements' => $ajustements,
            'compte_bancaire' => $iban === '' ? null : substr($iban, 0, 4).' •••• •••• '.substr($iban, -4),
        ];
    }

    /** Le professeur conteste les saisies confirmées qu'il n'a pas encore signées. */
    public function contester(Professeur $prof, int $annee, int $mois, string $motif, User $auteur): int
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $motif, $auteur) {
            $cibles = $this->lignes($prof, $annee, $mois)->lockForUpdate()->get()
                ->filter(fn (Timesheet $t) => $t->statut_validation === Timesheet::STATUT_CONFIRME && $t->signature_professeur === null);
            if ($cibles->isEmpty()) {
                throw RegleMetierException::invalide('Aucune saisie à contester : seules les saisies confirmées et non encore signées peuvent l\'être.');
            }
            foreach ($cibles as $t) {
                $t->update(['statut_validation' => Timesheet::STATUT_CONTESTE]);
                $this->audit($t, $auteur, TimesheetAudit::ACTION_CONTESTATION, Timesheet::STATUT_CONFIRME, Timesheet::STATUT_CONTESTE, $motif);
            }

            return $cibles->count();
        });
    }

    /** La direction traite la contestation : les saisies contestées repassent en revue (« soumis ») avec une réponse. */
    public function traiterContestation(Professeur $prof, int $annee, int $mois, string $reponse, User $auteur): int
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $reponse, $auteur) {
            $cibles = $this->lignes($prof, $annee, $mois)->lockForUpdate()->get()
                ->filter(fn (Timesheet $t) => $t->statut_validation === Timesheet::STATUT_CONTESTE);
            if ($cibles->isEmpty()) {
                throw RegleMetierException::invalide('Aucune contestation à traiter pour ce mois.');
            }
            foreach ($cibles as $t) {
                $t->update(['statut_validation' => Timesheet::STATUT_SOUMIS, 'signature_professeur' => null]);
                $this->audit($t, $auteur, TimesheetAudit::ACTION_REPONSE, Timesheet::STATUT_CONTESTE, Timesheet::STATUT_SOUMIS, $reponse);
            }

            return $cibles->count();
        });
    }

    /**
     * TS-02 : la direction renvoie en brouillon les lignes simplement soumises (pas encore validées ni signées).
     * Une ligne validée, signée, contestée ou incluse dans un PDF ne peut plus être rouverte : correction sur le mois suivant.
     */
    public function remettreEnBrouillon(Professeur $prof, int $annee, int $mois, string $motif, User $auteur): int
    {
        return DB::transaction(function () use ($prof, $annee, $mois, $motif, $auteur) {
            $cibles = $this->lignes($prof, $annee, $mois)->lockForUpdate()->get()
                ->filter(fn (Timesheet $t) => $this->rouvrable($t));
            if ($cibles->isEmpty()) {
                throw RegleMetierException::invalide(self::MSG_AUCUNE);
            }
            foreach ($cibles as $t) {
                $t->update(['statut_validation' => Timesheet::STATUT_BROUILLON]);
                TimesheetAudit::create([
                    'timesheet_id' => $t->id, 'professeur_id' => $t->professeur_id, 'user_id' => $auteur->id,
                    'action' => TimesheetAudit::ACTION_REMISE_BROUILLON,
                    'avant' => ['statut' => Timesheet::STATUT_SOUMIS], 'apres' => ['statut' => Timesheet::STATUT_BROUILLON], 'motif' => $motif,
                ]);
            }

            return $cibles->count();
        });
    }

    private function rouvrable(Timesheet $t): bool
    {
        return $t->statut_validation === Timesheet::STATUT_SOUMIS && $t->signature_professeur === null;
    }

    /** État pour l'écran directeur : l'action est-elle possible, sinon pourquoi. */
    public function etatRemiseBrouillon(Professeur $prof, int $annee, int $mois): array
    {
        $n = $this->lignes($prof, $annee, $mois)->get()->filter(fn (Timesheet $t) => $this->rouvrable($t))->count();

        return ['possible' => $n > 0, 'lignes' => $n, 'raison' => null];
    }

    private const MSG_AUCUNE = 'Remise en brouillon impossible : seules les lignes soumises, ni validées ni signées, peuvent être rouvertes. Les corrections se font dans la timesheet du mois suivant.';

    private function audit(Timesheet $t, User $auteur, string $action, string $de, string $vers, string $motif): void
    {
        TimesheetAudit::create([
            'timesheet_id' => $t->id,
            'professeur_id' => $t->professeur_id,
            'user_id' => $auteur->id,
            'action' => $action,
            'avant' => ['statut' => $de],
            'apres' => ['statut' => $vers],
            'motif' => $motif,
        ]);
    }
}
