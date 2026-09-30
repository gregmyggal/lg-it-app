<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée une classe SANS sessions (utiliser ClasseSessionGenerator ou CourseSession::factory()
 * pour en obtenir). Année + période 1 sont créées si non fournies.
 *
 * @extends Factory<Classe>
 */
class ClasseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cours_id' => Cours::factory(),
            'annee_scolaire_id' => fn () => AnneeScolaire::factory()->create()->id,
            'periode_id' => fn (array $attrs) => Periode::factory()->create([
                'annee_scolaire_id' => $attrs['annee_scolaire_id'],
            ])->id,
            'jour_semaine' => 3,
            'heure_debut' => '14:00',
            'heure_fin' => '17:00',
            'lieu' => 'Salle A',
            'date_premiere_session' => '2026-10-07',
            'statut' => Classe::STATUT_ACTIVE,
        ];
    }
}
