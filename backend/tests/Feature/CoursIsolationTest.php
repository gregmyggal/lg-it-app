<?php

namespace Tests\Feature;

use App\Models\Cours;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Catalogue : un professeur ne voit que les cours publiés où il a une classe active (plus de logique par type de cours). */
class CoursIsolationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    public function test_un_professeur_ne_voit_que_les_cours_de_ses_classes_actives(): void
    {
        $alice = Professeur::factory()->create();
        $react = Cours::factory()->create(['statut' => 'publish']);
        $autre = Cours::factory()->create(['statut' => 'publish']);
        $brouillon = Cours::factory()->create(['statut' => 'draft']);
        $annee = $this->annee();
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->assigner($this->classeAvecSessions($annee, ['cours_id' => $react->id]), $alice);
        $service->assigner($this->classeAvecSessions($annee, ['cours_id' => $brouillon->id, 'jour_semaine' => 6, 'date_premiere_session' => '2026-10-10']), $alice);

        Sanctum::actingAs($alice->user);
        $ids = collect($this->getJson('/api/cours')->assertOk()->json())->pluck('id')->all();

        $this->assertSame([$react->id], $ids);
        $this->assertNotContains($autre->id, $ids);
        $this->getJson("/api/cours/{$react->id}")->assertOk();
        $this->getJson("/api/cours/{$autre->id}")->assertForbidden();
    }

    public function test_le_staff_voit_tout_le_catalogue_et_un_professeur_sans_classe_rien(): void
    {
        Cours::factory()->count(3)->create(['statut' => 'publish']);

        $this->actingAsRole('directeur');
        $this->getJson('/api/cours')->assertOk()->assertJsonCount(3);

        Sanctum::actingAs(Professeur::factory()->create()->user);
        $this->getJson('/api/cours')->assertOk()->assertJsonCount(0);
    }
}
