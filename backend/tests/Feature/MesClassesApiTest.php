<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Portail « Mes classes » : classes actives, co-professeurs, situation par session (assignée / remplacée / remplaçant). */
class MesClassesApiTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    private Professeur $alice;

    private Professeur $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        $this->bob = Professeur::factory()->create();
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->assigner($this->classe, $this->alice, ['role' => 'principal']);
        $service->assigner($this->classe, $this->bob, ['role' => 'co_enseignant']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mes_classes_liste_la_classe_avec_co_professeurs_et_prochaine_session(): void
    {
        Sanctum::actingAs($this->alice->user);

        $data = $this->getJson('/api/mes-classes')->assertOk()->assertJsonCount(1, 'data')->json('data.0');

        $this->assertSame('principal', $data['role']);
        $this->assertTrue($data['actif']);
        $this->assertSame($this->classe->id, $data['classe']['id']);
        $this->assertSame([$this->bob->id], collect($data['co_professeurs'])->pluck('id')->all());
        $this->assertNotNull($data['classe']['prochaine_session']);
    }

    public function test_classes_terminees_uniquement_avec_inclure_terminees(): void
    {
        app(ClasseProfesseurAssignmentService::class)
            ->terminer(app(ClasseProfesseurAssignmentService::class)->assignation($this->classe, $this->alice), '2026-09-30');
        Carbon::setTestNow('2026-10-02 10:00:00');
        Sanctum::actingAs($this->alice->user);

        $this->getJson('/api/mes-classes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/mes-classes?inclure_terminees=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actif', false);
    }

    public function test_situations_assignee_remplace_par_et_remplacant_de(): void
    {
        $carol = Professeur::factory()->create();
        $seance5 = $this->classe->sessions()->where('seance_numero', 5)->first();
        app(SessionReplacementService::class)->remplacer($seance5, $this->alice->id, $carol->id);

        Sanctum::actingAs($this->alice->user);
        $sessions = collect($this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertOk()->json('data'))->keyBy('seance_numero');
        $this->assertSame('remplace_par', $sessions[5]['ma_situation']['type']);
        $this->assertSame($carol->id, $sessions[5]['ma_situation']['professeur']['id']);
        $this->assertSame('assignee', $sessions[6]['ma_situation']['type']);
        $this->assertSame([$this->bob->id], collect($sessions[6]['co_professeurs'])->pluck('id')->all());

        Sanctum::actingAs($carol->user);
        $this->getJson('/api/mes-classes')->assertOk()
            ->assertJsonCount(0, 'data') // pas assignée à la classe
            ->assertJsonCount(1, 'remplacements')
            ->assertJsonPath('remplacements.0.seance_numero', 5);
        $sessions = collect($this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertOk()->json('data'))->keyBy('seance_numero');
        $this->assertSame([5], $sessions->keys()->all()); // Carol ne voit que la séance qu'elle remplace
        $this->assertSame('remplacant_de', $sessions[5]['ma_situation']['type']);
        $this->assertSame($this->alice->id, $sessions[5]['ma_situation']['professeur']['id']);
        $this->assertSame([$this->bob->id], collect($sessions[5]['co_professeurs'])->pluck('id')->all());
    }

    public function test_403_pour_staff_sans_profil_professeur_et_pour_la_classe_d_un_autre(): void
    {
        $this->actingAsRole('directeur');
        $this->getJson('/api/mes-classes')->assertForbidden();
        $this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertForbidden();

        $autre = Professeur::factory()->create();
        Sanctum::actingAs($autre->user);
        $this->getJson('/api/mes-classes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertForbidden();
    }

    public function test_401_sans_authentification(): void
    {
        $this->getJson('/api/mes-classes')->assertUnauthorized();
    }
}
