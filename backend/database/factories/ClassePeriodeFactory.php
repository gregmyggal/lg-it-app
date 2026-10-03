<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\Cours;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Période de classe SANS sessions. Si `classe_id` est fourni, la période P1 de l'année de la classe est utilisée.
 *
 * @extends Factory<ClassePeriode>
 */
class ClassePeriodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classe_id' => fn () => Classe::factory()->sansPeriode()->create()->id,
            'periode_id' => fn (array $attrs) => Periode::firstOrCreate(
                ['annee_scolaire_id' => Classe::findOrFail($attrs['classe_id'])->annee_scolaire_id, 'numero' => 1],
                ['date_debut' => '2026-08-24', 'date_fin' => '2027-02-19']
            )->id,
            'cours_id' => Cours::factory(),
            'date_premiere_session' => '2026-10-07',
            'statut' => ClassePeriode::STATUT_ACTIVE,
        ];
    }
}
