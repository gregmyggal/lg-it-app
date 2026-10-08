<?php

namespace Database\Factories;

use App\Models\Employeur;
use App\Models\Professeur;
use App\Models\ProfesseurEmployeurMois;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfesseurEmployeurMoisFactory extends Factory
{
    protected $model = ProfesseurEmployeurMois::class;

    public function definition(): array
    {
        return [
            'professeur_id' => Professeur::factory(),
            'annee' => 2026,
            'mois' => 10,
            'employeur_id' => fn () => Employeur::parDefaut()->id,
            'source' => ProfesseurEmployeurMois::SOURCE_EXPLICITE,
            'version' => 1,
        ];
    }
}
