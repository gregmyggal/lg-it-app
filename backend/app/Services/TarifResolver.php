<?php

namespace App\Services;

use App\Models\Timesheet;
use Illuminate\Support\Collection;

/** Tarif horaire en vigueur à une date et montant d'une saisie, à partir des tarifs déjà chargés d'un professeur. */
class TarifResolver
{
    public function __construct(private readonly TimesheetParametreService $parametres) {}

    /** @param Collection<int, \App\Models\ProfesseurTarif> $tarifs */
    public function tarifA(string $date, Collection $tarifs): ?float
    {
        $tarif = $tarifs
            ->filter(fn ($x) => $x->date_debut->toDateString() <= $date && ($x->date_fin === null || $x->date_fin->toDateString() > $date))
            ->sortByDesc('date_debut')->first();

        return $tarif ? (float) $tarif->tarif_horaire_eur : null;
    }

    /** Montant d'une saisie : heures × tarif horaire, ou nombre × forfait annuel pour un frais de déplacement. */
    public function montant(Timesheet $t, Collection $tarifs): ?float
    {
        $unite = $this->unite($t, $tarifs);

        return $unite === null ? null : round((float) $t->nombre_heures * $unite, 2);
    }

    /** Montant unitaire de la saisie (tarif horaire, ou forfait d'un déplacement) ; null si aucun tarif. */
    public function unite(Timesheet $t, Collection $tarifs): ?float
    {
        if ($t->type_activite === TimesheetService::TYPE_DEPLACEMENT) {
            return $this->parametres->fraisDeplacement((int) $t->date_prestation->format('Y'));
        }

        return $this->tarifA($t->date_prestation->toDateString(), $tarifs);
    }
}
