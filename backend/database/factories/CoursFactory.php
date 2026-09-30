<?php

namespace Database\Factories;

use App\Models\Cours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cours>
 */
class CoursFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titre = $this->faker->word();

        return [
            'titre' => ucfirst($titre),
            'slug' => $this->faker->unique()->slug(),
            'contenu' => $this->faker->paragraph(),
            'extrait' => $this->faker->sentence(),
            'image_path' => null,
            'url_logiscool' => $this->faker->url(),
            'menu_order' => $this->faker->randomNumber(),
            'statut' => 'draft',
        ];
    }

    public function published(): self
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'publish',
        ]);
    }
}
