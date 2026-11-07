<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TS-07 : la direction/admin ajoute des heures libres, chacune avec son commentaire, au mois EN BROUILLON d'un
 * professeur. Les lignes sont créées en brouillon au nom du professeur (qui les voit et soumet son mois) et tracées.
 */
class TimesheetAjoutDirectionService
{
    public const TYPES = [TimesheetService::TYPE_ANIMATION, TimesheetService::TYPE_COURS, TimesheetService::TYPE_PREPARATION];

    /** @return array{possible: bool, raison_impossible: ?string} le mois accepte-t-il un ajout ? (aucune saisie soumise ou plus avancée) */
    public function etat(Professeur $prof, int $annee, int $mois): array
    {
        [$debut, $fin] = TimesheetVersionService::bornes($annee, $mois);
        $verrouille = Timesheet::where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut, $fin])
            ->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)->exists();

        return ['possible' => ! $verrouille, 'raison_impossible' => $verrouille ? 'mois_soumis' : null];
    }

    /**
     * @param  list<array{date_prestation: string, type_activite: string, nombre_heures: float|int|string, commentaire: string}>  $lignes
     * @return Collection<int, Timesheet>
     *
     * @throws RegleMetierException 422 mois déjà soumis ou date hors du mois
     */
    public function ajouter(Professeur $prof, int $annee, int $mois, User $auteur, array $lignes): Collection
    {
        [$debut, $fin] = TimesheetVersionService::bornes($annee, $mois);

        return DB::transaction(function () use ($prof, $annee, $mois, $auteur, $lignes, $debut, $fin) {
            // Verrou sur les saisies du mois : un ajout ne peut pas croiser une soumission concurrente.
            Timesheet::where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut, $fin])->lockForUpdate()->get();
            if (! $this->etat($prof, $annee, $mois)['possible']) {
                throw RegleMetierException::invalide('Ce mois est déjà soumis : utilisez « Adapter » sur les lignes existantes.', ['lignes' => ['Mois déjà soumis.']]);
            }

            return collect($lignes)->map(function (array $l, int $i) use ($prof, $auteur, $debut, $fin) {
                if ($l['date_prestation'] < $debut || $l['date_prestation'] > $fin) {
                    throw RegleMetierException::invalide('La date doit être dans le mois concerné.', ["lignes.$i.date_prestation" => ['Date hors du mois.']]);
                }
                $t = Timesheet::create([
                    'professeur_id' => $prof->id,
                    'date_prestation' => $l['date_prestation'],
                    'type_activite' => $l['type_activite'],
                    'nombre_heures' => $l['nombre_heures'],
                    'commentaire' => trim($l['commentaire']),
                    'ajoute_par_user_id' => $auteur->id,
                    'statut_validation' => Timesheet::STATUT_BROUILLON,
                ]);
                TimesheetAudit::create([
                    'timesheet_id' => $t->id, 'professeur_id' => $prof->id, 'user_id' => $auteur->id,
                    'action' => TimesheetAudit::ACTION_AJOUT_DIRECTION,
                    'avant' => [], 'apres' => ['date_prestation' => $l['date_prestation'], 'type_activite' => $l['type_activite'], 'nombre_heures' => (float) $l['nombre_heures']],
                    'motif' => trim($l['commentaire']),
                ]);

                return $t;
            });
        });
    }
}
