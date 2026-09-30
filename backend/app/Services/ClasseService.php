<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

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
     * Suppression physique seulement si la classe n'a aucune dépendance : pas de timesheet,
     * aucune session passée, commencée, terminée ou annulée. Sinon 409 (archiver / annuler).
     */
    public function supprimer(Classe $classe): void
    {
        $sessions = CourseSession::where('classe_id', $classe->id);
        $aujourdhui = now('Europe/Brussels')->toDateString();

        $aDesTimesheets = Timesheet::whereIn('course_session_id', (clone $sessions)->select('id'))->exists();
        $aDesSessionsVecues = (clone $sessions)
            ->where(fn ($q) => $q->where('statut', '!=', CourseSession::STATUT_PLANIFIEE)->orWhere('date', '<', $aujourdhui))
            ->exists();

        if ($aDesTimesheets || $aDesSessionsVecues) {
            throw RegleMetierException::conflit(
                'Cette classe a des sessions passées, annulées ou des heures encodées : archivez-la plutôt que de la supprimer.'
            );
        }

        DB::transaction(function () use ($classe) {
            // Les FK sont en restrict : on retire d'abord les sessions (bis remplaçants avant remplacés).
            CourseSession::where('classe_id', $classe->id)->whereNotNull('remplace_session_id')->delete();
            CourseSession::where('classe_id', $classe->id)->delete();
            $classe->delete();
        });
    }
}
