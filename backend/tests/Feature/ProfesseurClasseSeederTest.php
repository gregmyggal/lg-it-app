<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\SessionProfesseur;
use Database\Seeders\AnneeScolaireSeeder;
use Database\Seeders\ClasseSeeder;
use Database\Seeders\ProfesseurClasseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProfesseurClasseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_de_demo_assigne_alice_bob_et_remplace_la_seance_5_de_facon_idempotente(): void
    {
        Carbon::setTestNow('2026-09-30 10:00:00');
        $alice = Professeur::factory()->create(['email' => 'alice@test.com']);
        $bob = Professeur::factory()->create(['email' => 'bob@test.com']);

        foreach ([1, 2] as $_) {
            $this->seed(AnneeScolaireSeeder::class);
            $this->seed(ClasseSeeder::class);
            $this->seed(ProfesseurClasseSeeder::class);
        }

        $this->assertDatabaseCount('professeur_classe', 2);
        $remplacees = SessionProfesseur::where('professeur_id', $alice->id)->where('remplace', true)->get();
        $this->assertCount(1, $remplacees);
        $this->assertSame($bob->id, $remplacees->first()->remplace_par_professeur_id);
        $this->assertSame(1, SessionProfesseur::where('origine', 'remplacement')->count());

        Carbon::setTestNow();
    }
}
