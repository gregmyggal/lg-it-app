<?php

namespace Database\Factories;

use App\Models\Professeur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Professeur + compte utilisateur de rôle « professeur » (créé si non fourni). */
class ProfesseurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create(['role' => 'professeur'])->id,
            'prenom' => fake()->firstName(),
            'nom' => fake()->lastName(),
            'email' => fn (array $attrs) => User::find($attrs['user_id'])->email,
            'statut' => 'actif',
            'date_entree' => '2026-01-01',
        ];
    }
}
