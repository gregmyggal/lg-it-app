<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use Database\Seeders\AnneeScolaireSeeder;
use Database\Seeders\ClasseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScolariteSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_creent_lannee_2026_2027_et_les_classes_react_de_facon_idempotente(): void
    {
        foreach ([1, 2] as $_) {
            $this->seed(AnneeScolaireSeeder::class);
            $this->seed(ClasseSeeder::class);
        }

        $this->assertDatabaseCount('annees_scolaires', 1);
        $this->assertDatabaseCount('periodes', 2);
        $this->assertDatabaseCount('calendrier_scolaire', 14);
        $this->assertSame(2, Classe::count());
        $this->assertSame(28, CourseSession::count());

        $classes = Classe::with('periode')->orderBy('jour_semaine')->get();
        $this->assertSame([3, 6], $classes->pluck('jour_semaine')->all());
        $this->assertSame(['14:00:00', '09:00:00'], $classes->pluck('heure_debut')->all());
        foreach ($classes as $classe) {
            $derniere = $classe->sessions()->get()->last();
            $this->assertTrue($derniere->date <= $classe->periode->date_fin);
        }
    }
}
