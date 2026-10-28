<?php

namespace App\Services;

use App\Models\Professeur;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimesheetSignatureService
{
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
     * moins une attend sa signature (une saisie ajustée après signature la perd ; les autres restent signées) ou le
    
     * contenu signé a changé (SIG-01) et aucune contestation en cours. Le plafond journalier ne conditionne PAS la
     * signature : un mois validé par la direction est toujours signable, lissé ou non.
     * La signature elle-même : SignatureNumeriqueService::signer().
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

        // SIG-01 : tout est signé mais le contenu signé a changé depuis (ex. compte bancaire) → nouvelle signature.
        $aResigner = $nonConfirmees->isEmpty() && $aSigner->isEmpty() && $timesheets->isNotEmpty()
            && ($prof = Professeur::find($professeurId)) && app(SignatureNumeriqueService::class)->aResigner($prof, $year, $month);
        if ($aResigner) {
            $aSigner = collect([true]);
        }

        // TS-04 T1c : un mois validé ne se signe que sur sa version validée, encore à jour.
        $garde = ($prof = Professeur::find($professeurId)) && $timesheets->isNotEmpty()
            ? app(TimesheetVersionService::class)->garde($prof, $year, $month)
            : ['ok' => true, 'erreur' => null];

        return [
            'can_sign' => $nonConfirmees->isEmpty() && $aSigner->isNotEmpty() && $garde['ok'],
            'errors' => array_values(array_filter([
                $garde['erreur'],
                $contestees->isNotEmpty() ? 'Contestation en cours : en attente de la direction' : null,
                $nonConfirmees->count() > $contestees->count() ? ($nonConfirmees->count() - $contestees->count()).' entrée(s) non confirmées' : null,
                $nonConfirmees->isEmpty() && $aSigner->isEmpty() && $timesheets->isNotEmpty() ? 'Mois déjà signé' : null,
            ])),
            'warnings' => [],
        ];
    }
}
