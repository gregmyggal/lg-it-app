<?php

namespace Database\Seeders;

use App\Models\AnneeScolaire;
use App\Services\AnneeScolaireService;
use App\Services\FwbCalendarImporter;
use Illuminate\Database\Seeder;

/**
 * Année 2026-2027 (rentrée FWB 24/08/2026 → 02/07/2027), 2 périodes cohérentes avec le calendrier :
 * P1 = 24/08/2026 → 19/02/2027 (avant le congé de détente), P2 = 22/02/2027 → 02/07/2027.
 * Un cycle de 14 séances hebdomadaires tient dans chaque période, vacances comprises.
 */
class AnneeScolaireSeeder extends Seeder
{
    public function run(AnneeScolaireService $service, FwbCalendarImporter $importer): void
    {
        $annee = AnneeScolaire::where('libelle', '2026-2027')->first()
            ?? $service->creer([
                'libelle' => '2026-2027',
                'date_debut' => '2026-08-24',
                'date_fin' => '2027-07-02',
                'statut' => AnneeScolaire::STATUT_ACTIVE,
                'periodes' => [
                    ['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-19'],
                    ['numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02'],
                ],
            ]);

        $resultat = $importer->importer($annee);
        $this->command?->info("Calendrier FWB {$annee->libelle} : {$resultat['creees']} créée(s), {$resultat['ignorees']} ignorée(s).");
    }
}
