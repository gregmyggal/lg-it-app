<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Endpoints d'assignation : mêmes résultats depuis la classe et depuis le professeur (AC-12/AC-13). */
class ClasseProfesseurApiTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_401_sans_authentification(): void
    {
        $this->getJson("/api/classes/{$this->classe->id}/professeurs")->assertUnauthorized();
        $this->postJson("/api/classes/{$this->classe->id}/professeurs", [])->assertUnauthorized();
    }

    public function test_403_pour_un_professeur_en_ecriture(): void
    {
        $prof = Professeur::factory()->create();
        Sanctum::actingAs($prof->user);

        $this->postJson("/api/classes/{$this->classe->id}/professeurs/apercu", ['professeur_id' => $prof->id])->assertForbidden();
        $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => $prof->id])->assertForbidden();
        $this->postJson("/api/professeurs/{$prof->id}/classes", ['classe_id' => $this->classe->id])->assertForbidden();
    }

    public function test_validation_422(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson("/api/classes/{$this->classe->id}/professeurs", [])->assertStatus(422)->assertJsonValidationErrors('professeur_id');
        $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => 999999])->assertStatus(422);
        $this->postJson("/api/classes/{$this->classe->id}/professeurs", [
            'professeur_id' => Professeur::factory()->create()->id,
            'role' => 'chef',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_assigner_depuis_la_classe_puis_index_update_et_terminer(): void
    {
        $this->actingAsRole('directeur');
        $alice = Professeur::factory()->create();

        $apercu = $this->postJson("/api/classes/{$this->classe->id}/professeurs/apercu", ['professeur_id' => $alice->id])
            ->assertOk()->json('data');
        $this->assertSame(14, $apercu['sessions_assignees']);
        $this->assertSame(0, SessionProfesseur::count()); // aperçu : aucune écriture

        $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => $alice->id, 'role' => 'principal'])
            ->assertCreated()
            ->assertJsonPath('recapitulatif.sessions_assignees', 14)
            ->assertJsonPath('data.role', 'principal')
            ->assertJsonPath('data.actif', true);

        $this->getJson("/api/classes/{$this->classe->id}/professeurs")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nb_sessions_assignees', 14);

        $this->putJson("/api/classes/{$this->classe->id}/professeurs/{$alice->id}", ['role' => 'co_enseignant'])
            ->assertOk()->assertJsonPath('data.role', 'co_enseignant');

        $this->deleteJson("/api/classes/{$this->classe->id}/professeurs/{$alice->id}", ['date_fin' => '2026-09-30'])
            ->assertOk()->assertJsonPath('data.actif', true)->assertJsonPath('recapitulatif.sessions_retirees', 14);
        $this->assertSame(0, SessionProfesseur::count());
    }

    public function test_resultat_identique_depuis_la_classe_et_depuis_le_professeur(): void
    {
        $this->actingAsRole('directeur');
        $alice = Professeur::factory()->create();
        $bob = Professeur::factory()->create();

        $depuisClasse = $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => $alice->id, 'role' => 'principal']);
        $depuisProf = $this->postJson("/api/professeurs/{$bob->id}/classes", ['classe_id' => $this->classe->id, 'role' => 'principal']);

        $depuisClasse->assertCreated();
        $depuisProf->assertCreated();
        $this->assertSame($depuisClasse->json('recapitulatif'), $depuisProf->json('recapitulatif'));
        $this->assertSame(14, SessionProfesseur::where('professeur_id', $alice->id)->count());
        $this->assertSame(14, SessionProfesseur::where('professeur_id', $bob->id)->count());
        $this->assertSame(
            SessionProfesseur::where('professeur_id', $alice->id)->pluck('role', 'course_session_id')->values()->unique()->all(),
            SessionProfesseur::where('professeur_id', $bob->id)->pluck('role', 'course_session_id')->values()->unique()->all()
        );
    }

    public function test_apercu_et_modification_depuis_le_professeur(): void
    {
        $this->actingAsRole('admin');
        $alice = Professeur::factory()->create();

        $this->postJson("/api/professeurs/{$alice->id}/classes/apercu", ['classe_id' => $this->classe->id])
            ->assertOk()->assertJsonPath('data.sessions_assignees', 14);
        $this->postJson("/api/professeurs/{$alice->id}/classes", ['classe_id' => $this->classe->id])->assertCreated();
        $this->putJson("/api/professeurs/{$alice->id}/classes/{$this->classe->id}", ['role' => 'principal'])
            ->assertOk()->assertJsonPath('data.role', 'principal');
        $this->deleteJson("/api/professeurs/{$alice->id}/classes/{$this->classe->id}", ['date_fin' => '2026-09-30'])
            ->assertOk()->assertJsonPath('recapitulatif.sessions_retirees', 14);
        $this->assertDatabaseHas('professeur_classe', ['professeur_id' => $alice->id, 'classe_id' => $this->classe->id]);
    }

    public function test_conflit_d_horaire_renvoie_422_avec_la_liste_des_conflits(): void
    {
        $this->actingAsRole('directeur');
        $alice = Professeur::factory()->create();
        $autre = $this->classeAvecSessions($this->classe->anneeScolaire, ['heure_debut' => '15:00', 'heure_fin' => '18:00']);
        $this->postJson("/api/classes/{$autre->id}/professeurs", ['professeur_id' => $alice->id])->assertCreated();

        $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => $alice->id])
            ->assertStatus(422)->assertJsonCount(14, 'conflits');
        $this->postJson("/api/classes/{$this->classe->id}/professeurs/apercu", ['professeur_id' => $alice->id])
            ->assertOk()->assertJsonCount(14, 'data.conflits');
    }

    public function test_liste_des_classes_d_un_professeur_visible_par_lui_seul_ou_le_staff(): void
    {
        $alice = Professeur::factory()->create();
        $bob = Professeur::factory()->create();
        $this->actingAsRole('directeur');
        $this->postJson("/api/classes/{$this->classe->id}/professeurs", ['professeur_id' => $alice->id])->assertCreated();

        Sanctum::actingAs($alice->user);
        $this->getJson("/api/professeurs/{$alice->id}/classes")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/professeurs/{$bob->id}/classes")->assertForbidden();

        $this->actingAsRole('directeur');
        $this->getJson("/api/professeurs/{$alice->id}/classes")->assertOk()->assertJsonCount(1, 'data');
    }
}
