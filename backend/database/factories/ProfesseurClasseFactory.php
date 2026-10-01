<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée UNIQUEMENT la ligne d'assignation (sans propagation aux sessions) : pour tester le
 * comportement réel, passer par ClasseProfesseurAssignmentService.
 *
 * @extends Factory<ProfesseurClasse>
 */
class ProfesseurClasseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'professeur_id' => Professeur::factory(),
            'classe_id' => Classe::factory(),
            'role' => ProfesseurClasse::ROLE_CO_ENSEIGNANT,
            'date_debut' => '2026-08-24',
            'date_fin' => null,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn () => ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);
    }

    public function termine(string $date): static
    {
        return $this->state(fn () => ['date_fin' => $date]);
    }
}
