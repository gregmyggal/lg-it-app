<?php

namespace Database\Seeders;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use Illuminate\Database\Seeder;

class ProfesseurTarifSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère tous les professeurs
        $professeurs = Professeur::all();

        if ($professeurs->isEmpty()) {
            $this->command->warn('Aucun professeur trouvé — seeding de tarifs ignoré.');
            return;
        }

        // Crée un tarif de base pour chaque professeur
        // Format: 7,50 €/h (exemple Logiscool)
        foreach ($professeurs as $professeur) {
            ProfesseurTarif::create([
                'professeur_id' => $professeur->id,
                'tarif_horaire_eur' => 7.50,
                'date_debut' => now()->startOfMonth(),
                'date_fin' => null,
            ]);

            $this->command->line("✓ Tarif créé pour {$professeur->prenom} {$professeur->nom} : 7,50 €/h");
        }
    }
}
