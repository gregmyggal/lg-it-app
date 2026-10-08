<?php

namespace Database\Factories;

use App\Models\Employeur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Entité de test COMPLÈTE (coordonnées fictives mais valides : RPM de format correct, IBAN de test). */
class EmployeurFactory extends Factory
{
    protected $model = Employeur::class;

    public function definition(): array
    {
        return [
            'code' => 'test_'.fake()->unique()->lexify('????'),
            'nom' => fake()->company(),
            'rpm' => 'BE0123.456.789',
            'compte_bancaire' => 'BE68539007547034',
            'adresse' => 'Rue de Test 1 – 7000 MONS',
            'par_defaut' => false,
            'actif' => true,
            'couleur_badge' => 'vert',
        ];
    }
}
