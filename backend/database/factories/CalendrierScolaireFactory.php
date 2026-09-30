<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendrierScolaire> */
class CalendrierScolaireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'date_debut' => '2026-10-19',
            'date_fin' => '2026-11-01',
            'type' => CalendrierScolaire::TYPE_VACANCES,
            'libelle' => 'Vacances',
            'source' => CalendrierScolaire::SOURCE_ECOLE,
            'masque' => false,
        ];
    }

    public function fwb(?string $cle = null): static
    {
        return $this->state(fn () => [
            'source' => CalendrierScolaire::SOURCE_FWB,
            'cle_fwb' => $cle ?? $this->faker->unique()->slug(2),
        ]);
    }
}
