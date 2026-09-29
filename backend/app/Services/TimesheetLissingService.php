<?php

namespace App\Services;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimesheetLissingService
{
    private const MAX_MONTANT_PAR_JOUR = 44.02;

    /**
     * Calcule les montants pour tous les timesheets d'un mois
     * Retourne: ['timesheets' => [...], 'depassements' => [...]]
     */
    public function calculateMonthlyMontants(int $professeurId, int $year, int $month): array
    {
        $professeur = Professeur::findOrFail($professeurId);

        // Récupère tous les timesheets du mois
        $timesheets = Timesheet::where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $year)
            ->whereMonth('date_prestation', $month)
            ->orderBy('date_prestation')
            ->get();

        $montantsByDay = [];
        $result = [
            'timesheets' => [],
            'depassements' => [],
            'total_montant' => 0,
        ];

        foreach ($timesheets as $timesheet) {
            $tarif = ProfesseurTarif::effectiveAt(
                $professeurId,
                $timesheet->date_prestation
            );

            if (!$tarif) {
                // Pas de tarif disponible — garder le timesheet sans montant
                $result['timesheets'][] = [
                    'id' => $timesheet->id,
                    'date_prestation' => $timesheet->date_prestation,
                    'nombre_heures' => $timesheet->nombre_heures,
                    'type_activite' => $timesheet->type_activite,
                    'montant_brut' => null,
                    'erreur' => 'Aucun tarif disponible pour cette date',
                ];
                continue;
            }

            $montantBrut = round(
                $timesheet->nombre_heures * $tarif->tarif_horaire_eur,
                2
            );

            $day = $timesheet->date_prestation->format('Y-m-d');
            $montantsByDay[$day] = ($montantsByDay[$day] ?? 0) + $montantBrut;

            $result['timesheets'][] = [
                'id' => $timesheet->id,
                'date_prestation' => $timesheet->date_prestation,
                'nombre_heures' => $timesheet->nombre_heures,
                'type_activite' => $timesheet->type_activite,
                'montant_brut' => $montantBrut,
                'tarif_horaire' => $tarif->tarif_horaire_eur,
            ];

            $result['total_montant'] += $montantBrut;
        }

        // Détecte les jours qui dépassent le maximum
        foreach ($montantsByDay as $day => $montant) {
            if ($montant > self::MAX_MONTANT_PAR_JOUR) {
                $result['depassements'][] = [
                    'date' => $day,
                    'montant_total' => $montant,
                    'depassement' => round($montant - self::MAX_MONTANT_PAR_JOUR, 2),
                    'max_autorise' => self::MAX_MONTANT_PAR_JOUR,
                ];
            }
        }

        return $result;
    }

    /**
     * Propose un lissage pour un jour qui dépasse
     * Retourne les entrées à déplacer et le jour cible suggeré
     */
    public function proposeLissage(
        int $professeurId,
        string $dateDepassement,
        int $year,
        int $month
    ): array {
        $dateDepassement = Carbon::parse($dateDepassement);

        // Récupère toutes les infos du mois
        $monthData = $this->calculateMonthlyMontants($professeurId, $year, $month);

        if (empty($monthData['depassements'])) {
            return [
                'success' => false,
                'error' => 'Pas de dépassement détecté pour cette date',
            ];
        }

        // Cherche la dépassement spécifique
        $depassement = collect($monthData['depassements'])
            ->first(fn($d) => $d['date'] === $dateDepassement->format('Y-m-d'));

        if (!$depassement) {
            return [
                'success' => false,
                'error' => 'Dépassement non trouvé pour cette date',
            ];
        }

        // Récupère les timesheets du jour en dépassement
        $timesheetsDay = collect($monthData['timesheets'])
            ->filter(fn($t) => $t['date_prestation']->format('Y-m-d') === $dateDepassement->format('Y-m-d'));

        // Cherche un jour proche avec capacité disponible
        $dayToMove = $this->findCapacitableDay($dateDepassement, $month, $depassement['depassement']);

        return [
            'success' => true,
            'depassement' => $depassement,
            'timesheets_du_jour' => $timesheetsDay->values()->all(),
            'suggestion' => [
                'date_cible' => $dayToMove?->format('Y-m-d'),
                'quantite_a_deplacer_eur' => $depassement['depassement'],
                'note' => $dayToMove
                    ? "Déplacer {$depassement['depassement']}€ au {$dayToMove->format('d/m/Y')}"
                    : "Aucun jour disponible trouvé — ajustement manuel nécessaire",
            ],
        ];
    }

    /**
     * Effectue le lissage: déplace des heures d'un jour à l'autre
     */
    public function executeLissage(
        int $timesheetId,
        string $dateFrom,
        string $dateTo,
        float $montantADeplacer
    ): bool {
        $timesheet = Timesheet::findOrFail($timesheetId);

        // Calcule combien d'heures correspondent au montant à déplacer
        $tarif = ProfesseurTarif::effectiveAt(
            $timesheet->professeur_id,
            Carbon::parse($dateFrom)
        );

        if (!$tarif) {
            return false;
        }

        // heures = montant / tarif
        $heuresToMove = round($montantADeplacer / $tarif->tarif_horaire_eur, 2);

        if ($heuresToMove > $timesheet->nombre_heures) {
            return false;
        }

        // Crée une nouvelle entrée pour le jour cible
        $newTimesheet = Timesheet::create([
            'professeur_id' => $timesheet->professeur_id,
            'date_prestation' => $dateTo,
            'nombre_heures' => $heuresToMove,
            'type_activite' => $timesheet->type_activite,
            'cours_id' => $timesheet->cours_id,
            'commentaire' => "Lissé depuis " . Carbon::parse($dateFrom)->format('Y-m-d'),
            'statut_validation' => 'confirmé',
            'lissage_applique' => true,
        ]);

        // Réduit les heures du jour original
        $timesheet->nombre_heures -= $heuresToMove;
        $timesheet->lissage_applique = true;
        $timesheet->save();

        return true;
    }

    /**
     * Cherche un jour (±5j autour du jour en dépassement) avec capacité disponible
     */
    private function findCapacitableDay(Carbon $referenceDate, int $month, float $capacityNeeded): ?Carbon
    {
        $year = $referenceDate->year;

        // Cherche dans une plage de ±5 jours
        for ($offset = 1; $offset <= 5; $offset++) {
            // Essaie le jour avant
            $dayBefore = $referenceDate->copy()->subDays($offset);
            if ($dayBefore->month == $month && $this->dayHasCapacity($dayBefore, $year, $month, $capacityNeeded)) {
                return $dayBefore;
            }

            // Essaie le jour après
            $dayAfter = $referenceDate->copy()->addDays($offset);
            if ($dayAfter->month == $month && $this->dayHasCapacity($dayAfter, $year, $month, $capacityNeeded)) {
                return $dayAfter;
            }
        }

        return null;
    }

    /**
     * Vérifie si un jour a assez de capacité disponible
     */
    private function dayHasCapacity(Carbon $date, int $year, int $month, float $capacityNeeded): bool
    {
        $monthData = $this->calculateMonthlyMontants($date->day, $year, $month);

        $currentDay = collect($monthData['timesheets'])
            ->filter(fn($t) => $t['date_prestation']->format('Y-m-d') === $date->format('Y-m-d'))
            ->sum('montant_brut');

        $availableCapacity = self::MAX_MONTANT_PAR_JOUR - $currentDay;

        return $availableCapacity >= $capacityNeeded;
    }

    /**
     * Valide conformité annuelle (max 1.760,83€/an)
     */
    public function validateAnnualLimit(int $professeurId, int $year): array
    {
        $maxAnnuel = 1760.83;
        $totalAnnuel = 0;

        for ($month = 1; $month <= 12; $month++) {
            $monthData = $this->calculateMonthlyMontants($professeurId, $year, $month);
            $totalAnnuel += $monthData['total_montant'];
        }

        return [
            'total_annuel' => round($totalAnnuel, 2),
            'max_autorise' => $maxAnnuel,
            'conforme' => $totalAnnuel <= $maxAnnuel,
            'depassement' => $totalAnnuel > $maxAnnuel ? round($totalAnnuel - $maxAnnuel, 2) : 0,
        ];
    }
}
