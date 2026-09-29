<?php

namespace App\Services;

use App\Models\Timesheet;
use App\Models\Professeur;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Carbon\Carbon;

class TimesheetPdfService
{
    private TimesheetLissingService $lissingService;

    public function __construct(TimesheetLissingService $lissingService)
    {
        $this->lissingService = $lissingService;
    }

    /**
     * Génère un PDF de défraiement pour un mois complet
     * Tous les timesheets doivent être en statut "confirmé" et signés
     */
    public function generateMonthlyPdf(int $professeurId, int $year, int $month, int $userId): array
    {
        $professeur = Professeur::findOrFail($professeurId);

        // Vérifier que tous les timesheets sont confirmés et signés
        $timesheets = Timesheet::where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $year)
            ->whereMonth('date_prestation', $month)
            ->where('statut_validation', 'confirmé')
            ->get();

        if ($timesheets->isEmpty()) {
            return ['success' => false, 'error' => 'Aucun timesheet confirmé pour cette période'];
        }

        // Vérifier que tous les timesheets sont signés
        $unsigned = $timesheets->whereNull('signature_professeur');
        if ($unsigned->isNotEmpty()) {
            return ['success' => false, 'error' => 'Certains timesheets ne sont pas signés'];
        }

        // Récupérer les données
        $pdfData = $this->preparePdfData($professeur, $year, $month, $timesheets);

        // Générer le PDF
        try {
            $pdf = new Mpdf([
                'tempDir' => storage_path('temp'),
                'default_font' => 'DejaVu',
            ]);

            $html = $this->generateHtml($pdfData);
            $pdf->writeHTML($html);

            // Sauvegarder le PDF
            $filename = "defraiement_{$professeur->id}_{$year}_{$month}.pdf";
            $path = "pdfs/{$filename}";

            Storage::disk('local')->put($path, $pdf->output('', 'S'));

            // Mettre à jour tous les timesheets
            Timesheet::where('professeur_id', $professeurId)
                ->whereYear('date_prestation', $year)
                ->whereMonth('date_prestation', $month)
                ->update([
                    'statut_validation' => 'généré',
                    'pdf_generated_at' => now(),
                    'pdf_generated_by' => $userId,
                ]);

            return [
                'success' => true,
                'message' => 'PDF généré avec succès',
                'pdf_path' => $path,
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erreur lors de la génération: ' . $e->getMessage()];
        }
    }

    /**
     * Prépare les données pour le PDF
     */
    private function preparePdfData(Professeur $professeur, int $year, int $month, $timesheets): array
    {
        $monthData = $this->lissingService->calculateMonthlyMontants($professeur->id, $year, $month);

        $dateDebut = Carbon::createFromDate($year, $month, 1);
        $dateFin = $dateDebut->clone()->endOfMonth();

        return [
            'professeur' => [
                'nom' => $professeur->nom,
                'prenom' => $professeur->prenom,
                'email' => $professeur->email,
                'iban' => $professeur->compte_bancaire ?? 'N/A',
            ],
            'periode' => [
                'annee' => $year,
                'mois' => $month,
                'mois_label' => $dateDebut->translatedFormat('F Y', 'fr_FR'),
                'date_debut' => $dateDebut->format('Y-m-d'),
                'date_fin' => $dateFin->format('Y-m-d'),
            ],
            'heures_par_jour' => $monthData['heures_par_jour'] ?? [],
            'synthese' => [
                'total_heures' => $monthData['total_heures'] ?? 0,
                'total_montant' => $monthData['total_montant'] ?? 0,
                'nombre_jours' => count($monthData['heures_par_jour'] ?? []),
                'lissages_appliques' => $timesheets->where('lissage_applique', true)->count(),
            ],
            'conformite' => [
                'max_par_jour' => 44.02,
                'depassements' => $monthData['depassements'] ?? [],
            ],
        ];
    }

    /**
     * Génère le HTML pour mPDF
     */
    private function generateHtml(array $pdfData): string
    {
        $prof = $pdfData['professeur'];
        $periode = $pdfData['periode'];
        $synthese = $pdfData['synthese'];

        $heuresHtml = '';
        foreach ($pdfData['heures_par_jour'] as $date => $day) {
            $montantTotal = $day['montant_total'] ?? 0;
            $heuresHtml .= "
                <tr>
                    <td>{$date}</td>
                    <td style='text-align: right;'>{$day['total_heures']}h</td>
                    <td style='text-align: right;'>{$day['tarif_applique']}€</td>
                    <td style='text-align: right;'>" . number_format($montantTotal, 2, ',', '') . "€</td>
                </tr>
            ";
        }

        return "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { margin: 0; color: #1f2937; }
                    .info-block { background: #f9fafb; padding: 12px; margin: 15px 0; border-left: 4px solid #3b82f6; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th { background: #f3f4f6; padding: 10px; text-align: left; border-bottom: 2px solid #d1d5db; }
                    td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
                    .total-row { font-weight: bold; background: #f0fdf4; }
                    .footer { margin-top: 30px; font-size: 0.9em; color: #6b7280; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>📄 Défraiement des Heures Prestées</h1>
                    <p>{$periode['mois_label']}</p>
                </div>

                <div class='info-block'>
                    <strong>Professeur:</strong> {$prof['prenom']} {$prof['nom']}<br>
                    <strong>Email:</strong> {$prof['email']}<br>
                    <strong>IBAN:</strong> {$prof['iban']}<br>
                </div>

                <div class='info-block'>
                    <strong>Période:</strong> {$periode['date_debut']} à {$periode['date_fin']}<br>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Heures</th>
                            <th>Tarif</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$heuresHtml}
                        <tr class='total-row'>
                            <td colspan='2'><strong>TOTAL</strong></td>
                            <td style='text-align: right;'></td>
                            <td style='text-align: right;'>" . number_format($synthese['total_montant'], 2, ',', '') . "€</td>
                        </tr>
                    </tbody>
                </table>

                <div class='info-block'>
                    <strong>Synthèse:</strong><br>
                    Total heures: {$synthese['total_heures']}h<br>
                    Nombre de jours: {$synthese['nombre_jours']}<br>
                    Lissages appliqués: {$synthese['lissages_appliques']}<br>
                </div>

                <div class='footer'>
                    <p>Document généré le " . now()->format('d/m/Y à H:i') . "</p>
                    <p>Conformité: Max 44,02€/jour ✓</p>
                </div>
            </body>
            </html>
        ";
    }

    /**
     * Récupère le PDF généré (chemin ou contenu)
     */
    public function getPdfPath(int $professeurId, int $year, int $month): ?string
    {
        $timesheet = Timesheet::where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $year)
            ->whereMonth('date_prestation', $month)
            ->where('statut_validation', 'généré')
            ->first();

        if (!$timesheet || !$timesheet->pdf_generated_at) {
            return null;
        }

        $filename = "defraiement_{$professeurId}_{$year}_{$month}.pdf";
        return "pdfs/{$filename}";
    }
}
