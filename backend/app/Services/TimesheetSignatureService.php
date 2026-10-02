<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimesheetSignatureService
{
    /**
     * Signe un timesheet (confirmé → signé)
     * Enregistre le timestamp de signature
     */
    public function signTimesheet(Timesheet $timesheet, User $signee): bool
    {
        // Vérification: le signee doit être le propriétaire du timesheet
        if ($timesheet->professeur_id !== $signee->professeur?->id) {
            return false;
        }

        // Vérification: seuls les timesheets "confirme" peuvent être signés
        if ($timesheet->statut_validation !== 'confirme') {
            return false;
        }

        $timesheet->signature_professeur = now();
        $timesheet->save();

        return true;
    }

    /**
     * Prépare les données pour l'aperçu PDF du mois
     * Retourne tout ce qu'il faut pour le PDF
     */
    public function preparePdfData(int $professeurId, int $year, int $month): array
    {
        $lissingService = new TimesheetLissingService;
        $monthData = $lissingService->calculateMonthlyMontants($professeurId, $year, $month);

        $timesheets = DB::table('timesheets')
            ->where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $year)
            ->whereMonth('date_prestation', $month)
            ->orderBy('date_prestation')
            ->get();

        $professeur = DB::table('professeurs')->find($professeurId);
        $user = DB::table('users')->find($professeur->user_id);

        $dateDebut = Carbon::createFromDate($year, $month, 1);
        $dateFin = $dateDebut->copy()->endOfMonth();

        // Groupe par jour
        $parJour = [];
        foreach ($monthData['timesheets'] as $ts) {
            $jour = $ts['date_prestation']->format('Y-m-d');
            if (! isset($parJour[$jour])) {
                $parJour[$jour] = [
                    'date' => $ts['date_prestation'],
                    'montant_total' => 0,
                    'activites' => [],
                ];
            }

            if ($ts['montant_brut'] !== null) {
                $parJour[$jour]['montant_total'] += $ts['montant_brut'];
            }

            $parJour[$jour]['activites'][] = [
                'type' => $ts['type_activite'],
                'heures' => $ts['nombre_heures'],
                'tarif' => $ts['tarif_horaire'] ?? 'N/A',
                'montant' => $ts['montant_brut'] ?? 'N/A',
            ];
        }

        return [
            'professeur' => [
                'nom' => $professeur->nom,
                'prenom' => $professeur->prenom,
                'email' => $professeur->email,
                'compte_bancaire' => 'BE** **** **** ****', // Masqué, sera rempli par la DB si disponible
            ],
            'periode' => [
                'annee' => $year,
                'mois' => $month,
                'mois_label' => $dateDebut->locale('fr')->format('F Y'),
                'date_debut' => $dateDebut->format('Y-m-d'),
                'date_fin' => $dateFin->format('Y-m-d'),
            ],
            'heures_par_jour' => $parJour,
            'synthese' => [
                'total_heures' => collect($monthData['timesheets'])->sum('nombre_heures'),
                'total_montant' => $monthData['total_montant'],
                'nombre_jours_encodes' => count($parJour),
                'depassements' => $monthData['depassements'],
            ],
            'conformite' => [
                'max_par_jour' => app(TimesheetParametreService::class)->plafondJournalier($year),
                'conforme' => empty($monthData['depassements']),
                'depassements_detectes' => count($monthData['depassements']),
            ],
        ];
    }

    /**
     * Vérifie si un mois peut être signé par le professeur : toutes ses saisies sont confirmées (ou déjà générées), au
     * moins une attend sa signature (une saisie ajustée après signature la perd ; les autres restent signées), aucune
     * contestation en cours et aucun dépassement du plafond journalier.
     */
    public function canSignMonth(int $professeurId, int $year, int $month): array
    {
        $timesheets = DB::table('timesheets')
            ->where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $year)
            ->whereMonth('date_prestation', $month)
            ->get();

        $contestees = $timesheets->where('statut_validation', 'conteste');
        $nonConfirmees = $timesheets->whereNotIn('statut_validation', ['confirme', 'genere']);
        $aSigner = $timesheets->where('statut_validation', 'confirme')->whereNull('signature_professeur');

        $lissingService = new TimesheetLissingService;
        $monthData = $lissingService->calculateMonthlyMontants($professeurId, $year, $month);

        return [
            'can_sign' => $nonConfirmees->isEmpty() && $aSigner->isNotEmpty() && empty($monthData['depassements']),
            'errors' => array_values(array_filter([
                $contestees->isNotEmpty() ? 'Contestation en cours : en attente de la direction' : null,
                $nonConfirmees->count() > $contestees->count() ? ($nonConfirmees->count() - $contestees->count()).' entrée(s) non confirmées' : null,
                $nonConfirmees->isEmpty() && $aSigner->isEmpty() && $timesheets->isNotEmpty() ? 'Mois déjà signé' : null,
                count($monthData['depassements']) > 0 ? 'Dépassements détectés' : null,
            ])),
            'warnings' => [],
        ];
    }

    /**
     * Signe tous les timesheets confirmés d'un mois
     */
    public function signMonth(int $professeurId, int $year, int $month, User $signee): bool
    {
        if ($signee->isProfesseur() && $signee->professeur?->id !== $professeurId) {
            return false;
        }

        $canSign = $this->canSignMonth($professeurId, $year, $month);
        if (! $canSign['can_sign']) {
            return false;
        }

        // Transaction: sign all or nothing
        try {
            DB::transaction(function () use ($professeurId, $year, $month) {
                DB::table('timesheets')
                    ->where('professeur_id', $professeurId)
                    ->whereYear('date_prestation', $year)
                    ->whereMonth('date_prestation', $month)
                    ->where('statut_validation', 'confirme')
                    ->whereNull('signature_professeur')
                    ->update([
                        'signature_professeur' => now(),
                    ]);
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
