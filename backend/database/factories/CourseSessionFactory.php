<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourseSession> */
class CourseSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_id' => fn () => Classe::factory()->sansPeriode()->create()->id,
            'classe_periode_id' => fn (array $attrs) => ClassePeriode::factory()->create(['classe_id' => $attrs['classe_id']])->id,
            'seance_numero' => 1,
            'bis_rang' => 0,
            'date' => '2026-10-07',
            'heure_debut' => '14:00',
            'heure_fin' => '17:00',
            'lieu' => 'Salle A',
            'statut' => CourseSession::STATUT_PLANIFIEE,
        ];
    }

    public function annulee(string $motif = 'Absence du professeur'): static
    {
        return $this->state(fn () => [
            'statut' => CourseSession::STATUT_ANNULEE,
            'motif_annulation' => $motif,
            'cancelled_at' => now(),
        ]);
    }
}
