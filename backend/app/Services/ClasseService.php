<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClasseService
{
    /**
     * Met à jour créneau horaire / lieu / statut. Les modifications d'horaire et de lieu sont
     * répercutées sur les sessions À VENIR encore « planifiées » qui suivaient les valeurs de la classe
     * (une session déjà ajustée à la main, ou passée, n'est jamais touchée).
     */
    public function modifier(Classe $classe, array $data): Classe
    {
        return DB::transaction(function () use ($classe, $data) {
            $avant = $classe->only(['heure_debut', 'heure_fin', 'lieu']);
            $classe->update($data);

            $aujourdhui = now('Europe/Brussels')->toDateString();
            foreach (['heure_debut', 'heure_fin', 'lieu'] as $champ) {
                if (! array_key_exists($champ, $data) || $classe->wasChanged($champ) === false) {
                    continue;
                }

                CourseSession::query()
                    ->where('classe_id', $classe->id)
                    ->where('statut', CourseSession::STATUT_PLANIFIEE)
                    ->where('date', '>=', $aujourdhui)
                    ->where($champ, $avant[$champ])
                    ->update([$champ => $classe->{$champ}]);
            }

            return $classe->refresh();
        });
    }

    /**
     * Suppression physique. Sans forçage, seulement si la classe n'a aucun historique (heure encodée,
     * session passée, commencée, terminée ou annulée) ; sinon 409 avec le résumé de l'historique.
     * CLS-08 : avec forçage (admin/directeur, motif), la classe et toutes ses sessions sont supprimées ;
     * les heures encodées sont conservées, détachées de leur séance (FK nullOnDelete) et tracées dans
     * timesheet_audits. Interdit si une heure figure sur une fiche de défraiement générée.
     */
    public function supprimer(Classe $classe, ?string $motifForcage = null, ?User $auteur = null): int
    {
        $resume = $this->resumeHistorique($classe);
        $aUnHistorique = $resume['heures']['nb'] > 0 || $resume['seances']['passees'] > 0 || $resume['seances']['annulees'] > 0;

        if ($aUnHistorique && $resume['heures_generees'] > 0) {
            throw RegleMetierException::conflit(
                'Des heures de cette classe figurent sur une fiche de défraiement générée : déverrouillez le mois concerné avant de supprimer la classe, ou archivez-la.',
                ['resume' => $resume, 'forcable' => false]
            );
        }
        if ($aUnHistorique && $motifForcage === null) {
            throw RegleMetierException::conflit(
                'Cette classe a des sessions passées, annulées ou des heures encodées : archivez-la plutôt que de la supprimer.',
                ['resume' => $resume, 'forcable' => true]
            );
        }

        DB::transaction(function () use ($classe, $motifForcage, $auteur, $aUnHistorique) {
            if ($aUnHistorique) {
                $this->tracerHeuresDetachees($classe, (string) $motifForcage, $auteur);
            }
            // Les FK sont en restrict : on retire d'abord les sessions (bis remplaçants avant remplacés) ;
            // les périodes de classe partent en cascade avec la classe.
            CourseSession::where('classe_id', $classe->id)->whereNotNull('remplace_session_id')->delete();
            CourseSession::where('classe_id', $classe->id)->delete();
            $classe->delete();
        });

        if ($aUnHistorique) {
            Log::info('Classe supprimée par forçage (CLS-08)', [
                'classe_id' => $classe->id,
                'user_id' => $auteur?->id,
                'seances' => $resume['seances'],
                'heures' => $resume['heures'],
            ]);
        }

        return $resume['heures']['nb'];
    }

    /**
     * Ce qu'une suppression ferait perdre : séances passées / annulées / à venir, heures encodées par statut,
     * professeurs ayant des heures, heures déjà sur une fiche générée (bloquantes).
     *
     * @return array<string, mixed>
     */
    public function resumeHistorique(Classe $classe): array
    {
        $aujourdhui = now('Europe/Brussels')->toDateString();
        $sessions = CourseSession::where('classe_id', $classe->id)->get(['id', 'date', 'statut']);
        $annulees = $sessions->where('statut', CourseSession::STATUT_ANNULEE);
        $actives = $sessions->where('statut', '!=', CourseSession::STATUT_ANNULEE);
        $passees = $actives->filter(fn (CourseSession $s) => $s->statut !== CourseSession::STATUT_PLANIFIEE
            || $s->date->toDateString() < $aujourdhui);

        $heures = Timesheet::with('professeur:id,prenom,nom')
            ->whereIn('course_session_id', $sessions->pluck('id'))
            ->get(['id', 'professeur_id', 'nombre_heures', 'statut_validation']);

        return [
            'seances' => [
                'total' => $sessions->count(),
                'passees' => $passees->count(),
                'annulees' => $annulees->count(),
                'a_venir' => $actives->count() - $passees->count(),
            ],
            'heures' => [
                'nb' => $heures->count(),
                'total' => (float) $heures->sum('nombre_heures'),
                'par_statut' => $heures->groupBy('statut_validation')
                    ->map(fn ($groupe, $statut) => ['statut' => $statut, 'nb' => $groupe->count(), 'total' => (float) $groupe->sum('nombre_heures')])
                    ->values(),
            ],
            'heures_generees' => $heures->where('statut_validation', Timesheet::STATUT_GENERE)->count(),
            'professeurs' => $heures->pluck('professeur')->filter()->unique('id')
                ->map(fn ($p) => ['id' => $p->id, 'nom' => trim("{$p->prenom} {$p->nom}")])
                ->sortBy('nom')->values(),
        ];
    }

    /** Une ligne d'audit par heure encodée : classe, n° et date de la séance perdus au détachement. */
    private function tracerHeuresDetachees(Classe $classe, string $motif, ?User $auteur): void
    {
        $nom = ClasseResource::titreDe($classe->loadMissing('periodes.cours'));
        $heures = Timesheet::with('session:id,seance_numero,bis_rang,date')
            ->whereIn('course_session_id', CourseSession::where('classe_id', $classe->id)->select('id'))
            ->get();

        foreach ($heures as $heure) {
            TimesheetAudit::create([
                'timesheet_id' => $heure->id,
                'professeur_id' => $heure->professeur_id,
                'user_id' => $auteur?->id,
                'action' => TimesheetAudit::ACTION_CLASSE_SUPPRIMEE,
                'avant' => [
                    'classe_id' => $classe->id,
                    'classe' => $nom,
                    'course_session_id' => $heure->course_session_id,
                    'seance_numero' => $heure->session?->seance_numero,
                    'bis_rang' => $heure->session?->bis_rang,
                    'date_seance' => $heure->session?->date?->toDateString(),
                ],
                'apres' => ['course_session_id' => null],
                'motif' => $motif,
            ]);
        }
    }
}
