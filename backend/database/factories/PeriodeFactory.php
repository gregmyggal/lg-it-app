<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Periode> */
class PeriodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'numero' => 1,
            'date_debut' => '2026-08-24',
            'date_fin' => '2027-02-19',
        ];
    }
}
