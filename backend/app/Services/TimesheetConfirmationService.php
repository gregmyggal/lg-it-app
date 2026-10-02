<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
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
    public function __construct(private readonly TimesheetSyntheseMoisService $synthese) {}

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
            'derniere_reponse' => $reponse ? ['motif' => $reponse->motif, 'created_at' => $reponse->created_at] : null,
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
