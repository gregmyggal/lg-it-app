<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnneeScolaire> */
class AnneeScolaireFactory extends Factory
{
    public function definition(): array
    {
        $debut = $this->faker->unique()->numberBetween(2030, 2090);

        return [
            'libelle' => "{$debut}-".($debut + 1),
            'date_debut' => "{$debut}-08-24",
            'date_fin' => ($debut + 1).'-07-02',
            'statut' => AnneeScolaire::STATUT_ACTIVE,
        ];
    }

    /** Crée les 2 périodes standard (P1 : 24/08 → 19/02, P2 : 22/02 → 02/07). */
    public function avecPeriodes(): static
    {
        return $this->afterCreating(function (AnneeScolaire $annee) {
            $an = (int) $annee->date_debut->format('Y');
            $annee->periodes()->create(['numero' => 1, 'date_debut' => "{$an}-08-24", 'date_fin' => ($an + 1).'-02-19']);
            $annee->periodes()->create(['numero' => 2, 'date_debut' => ($an + 1).'-02-22', 'date_fin' => ($an + 1).'-07-02']);
        });
    }
}
