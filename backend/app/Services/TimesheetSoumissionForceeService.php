<?php

namespace App\Services;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;

/**
 * TS-05 : éligibilité d'une soumission forcée du mois d'un professeur, calculée par le serveur (le front ne déduit rien).
 * Possible = au moins une heure en brouillon et aucune saisie déjà soumise ou plus avancée.
 */
class TimesheetSoumissionForceeService
{
    public const RAISON_DEJA_SOUMIS = 'deja_soumis';

    public const RAISON_AUCUNE_HEURE = 'aucune_heure';

    public function __construct(private readonly TarifResolver $tarifs, private readonly TimesheetParametreService $parametres) {}

    /** @return array<string, mixed> */
    public function etat(Professeur $prof, int $annee, int $mois): array
    {
        [$debut, $fin] = TimesheetVersionService::bornes($annee, $mois);
        $lignes = Timesheet::where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut, $fin])->get();
        $brouillons = $lignes->where('statut_validation', Timesheet::STATUT_BROUILLON);
        $dejaSoumis = $lignes->contains(fn (Timesheet $t) => $t->statut_validation !== Timesheet::STATUT_BROUILLON);
        $possible = ! $dejaSoumis && $brouillons->isNotEmpty();

        $tarifsProf = ProfesseurTarif::where('professeur_id', $prof->id)->get();
        $plafond = $this->parametres->plafondJournalier($annee);
        $parJour = $brouillons->groupBy(fn (Timesheet $t) => $t->date_prestation->toDateString())
            ->map(fn ($l) => round((float) $l->sum(fn (Timesheet $t) => (float) $this->tarifs->montant($t, $tarifsProf)), 2));

        return [
            'possible' => $possible,
            'raison_impossible' => $possible ? null : ($dejaSoumis ? self::RAISON_DEJA_SOUMIS : self::RAISON_AUCUNE_HEURE),
            'lignes' => $brouillons->count(),
            'heures' => round((float) $brouillons->where('type_activite', '!=', TimesheetService::TYPE_DEPLACEMENT)->sum('nombre_heures'), 2),
            'total_eur' => round((float) $parJour->sum(), 2),
            'depassements' => $parJour->filter(fn ($m) => $m > $plafond)->keys()->values()->all(),
            'seances_sans_heures' => $possible ? $this->seancesSansHeures($prof, $debut, $fin, $lignes) : [],
            'mois_termine' => Carbon::parse($fin, 'Europe/Brussels')->endOfDay()->lt(Carbon::now('Europe/Brussels')),
        ];
    }

    /** Séances passées du mois où le professeur est attendu et n'a encodé aucune heure : non incluses dans la soumission. @return list<string> dates */
    private function seancesSansHeures(Professeur $prof, string $debut, string $fin, $lignes): array
    {
        $maintenant = Carbon::now('Europe/Brussels');
        $encodees = $lignes->pluck('course_session_id')->filter()->all();

        return CourseSession::query()
            ->whereBetween('date', [$debut, $fin])
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->where(fn ($q) => $q->where('date', '<', $maintenant->toDateString())
                ->orWhere(fn ($j) => $j->where('date', $maintenant->toDateString())->where('heure_fin', '<=', $maintenant->format('H:i:s'))))
            ->whereHas('sessionProfesseurs', fn ($l) => $l->where('professeur_id', $prof->id)->where('remplace', false))
            ->whereNotIn('id', $encodees)
            ->orderBy('date')->pluck('date')->map(fn ($d) => $d->toDateString())->all();
    }
}
