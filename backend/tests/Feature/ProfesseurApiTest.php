<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Non-régression : la liste et la fiche professeur fonctionnent avec le modèle classes (plus de relation « cours »). */
class ProfesseurApiTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    public function test_liste_et_fiche_professeur_exposent_le_nombre_de_classes_actives(): void
    {
        $alice = Professeur::factory()->create();
        $classe = $this->classeAvecSessions($this->annee());
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $alice);
        $this->actingAsRole('directeur');

        $liste = $this->getJson('/api/professeurs')->assertOk()->json();
        $this->assertSame(1, collect($liste)->firstWhere('id', $alice->id)['classes_count']);

        $this->getJson("/api/professeurs/{$alice->id}")
            ->assertOk()
            ->assertJsonPath('id', $alice->id)
            ->assertJsonPath('classes_count', 1);
    }
}
