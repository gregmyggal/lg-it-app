<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Isolation professeur (RG-5, AC-2) : Alice voit le mercredi, Bob le samedi d'un même cours. */
class IsolationProfesseurTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $mercredi;

    private Classe $samedi;

    private Professeur $alice;

    private Professeur $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $annee = $this->annee();
        $this->importFwb($annee);
        $cours = Cours::factory()->create();
        $this->mercredi = $this->classeAvecSessions($annee, ['cours_id' => $cours->id]);
        $this->samedi = $this->classeAvecSessions($annee, [
            'cours_id' => $cours->id, 'jour_semaine' => 6, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'date_premiere_session' => '2026-10-10',
        ]);
        $this->alice = Professeur::factory()->create();
        $this->bob = Professeur::factory()->create();
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->assigner($this->mercredi, $this->alice);
        $service->assigner($this->samedi, $this->bob);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alice_ne_voit_que_sa_classe_et_ses_sessions(): void
    {
        Sanctum::actingAs($this->alice->user);

        $ids = collect($this->getJson('/api/classes')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$this->mercredi->id], $ids);

        $this->getJson("/api/classes/{$this->mercredi->id}")->assertOk();
        $this->getJson("/api/classes/{$this->samedi->id}")->assertForbidden();
        $this->getJson("/api/classes/{$this->samedi->id}/sessions")->assertForbidden();
        $this->getJson("/api/classes/{$this->mercredi->id}/sessions")->assertOk()->assertJsonCount(14, 'data');

        $sessions = $this->getJson('/api/sessions?per_page=100')->assertOk()->json('data');
        $this->assertCount(14, $sessions);
        $this->assertSame([$this->mercredi->id], collect($sessions)->pluck('classe_id')->unique()->values()->all());
    }

    public function test_calendrier_filtre_pour_un_professeur(): void
    {
        Sanctum::actingAs($this->alice->user);

        $sessions = $this->getJson('/api/calendar/agenda?from_date=2026-10-01&to_date=2027-01-31')
            ->assertOk()->assertJsonPath('summary.total', 12)->json('data');

        $this->assertSame([$this->mercredi->id], collect($sessions)->pluck('classe_id')->unique()->values()->all());
    }

    public function test_sessions_et_professeurs_d_une_autre_classe_sont_interdits(): void
    {
        $sessionSamedi = $this->samedi->sessions()->first();
        Sanctum::actingAs($this->alice->user);

        $this->getJson("/api/sessions/{$sessionSamedi->id}/professeurs")->assertForbidden();
        $this->getJson("/api/classes/{$this->samedi->id}/professeurs")->assertForbidden();
    }

    public function test_un_professeur_ne_peut_rien_ecrire(): void
    {
        $session = $this->mercredi->sessions()->first();
        Sanctum::actingAs($this->alice->user);

        $this->postJson('/api/classes', [])->assertForbidden();
        $this->putJson("/api/classes/{$this->mercredi->id}", ['lieu' => 'X'])->assertForbidden();
        $this->postJson("/api/sessions/{$session->id}/remplacer", [
            'professeur_remplace_id' => $this->alice->id, 'professeur_remplacant_id' => $this->bob->id,
        ])->assertForbidden();
        $this->postJson("/api/sessions/{$session->id}/cancel", ['motif_annulation' => 'Test'])->assertForbidden();
    }

    public function test_aucun_tarif_n_est_expose_a_un_professeur(): void
    {
        Sanctum::actingAs($this->alice->user);

        foreach (['/api/classes', "/api/classes/{$this->mercredi->id}/professeurs", '/api/mes-classes'] as $url) {
            $corps = json_encode($this->getJson($url)->assertOk()->json());
            $this->assertStringNotContainsString('tarif', $corps, $url);
            $this->assertStringNotContainsString('taux', $corps, $url);
        }
    }

    public function test_can_access_cours_depend_d_une_assignation_active(): void
    {
        $cours = $this->mercredi->periodes()->first()->cours;
        $this->assertTrue($this->alice->canAccessCours($cours));
        $this->assertFalse($this->bob->canAccessCours(Cours::factory()->create()));

        ProfesseurClasse::where('professeur_id', $this->alice->id)->update(['date_fin' => '2026-09-01']);
        $this->assertFalse($this->alice->fresh()->canAccessCours($cours));
        // Bob enseigne le même cours (samedi) : accès actif
        $this->assertTrue($this->bob->canAccessCours($cours));
    }

    public function test_un_professeur_sans_classe_du_cours_ne_gere_pas_ses_liens(): void
    {
        $cours = $this->mercredi->periodes()->first()->cours;
        $sansClasse = Professeur::factory()->create();

        Sanctum::actingAs($sansClasse->user);
        // T4 (Q-T4-9) : lecture seule autorisée, écriture refusée.
        $this->getJson("/api/cours/{$cours->id}/liens")->assertOk()->assertJsonPath('peut_modifier', false);
        $this->postJson("/api/cours/{$cours->id}/liens", ['titre' => 'X', 'url' => 'https://example.com'])->assertForbidden();

        Sanctum::actingAs($this->alice->user);
        $this->getJson("/api/cours/{$cours->id}/liens")->assertOk();
    }

    public function test_anciennes_routes_professeur_cours_supprimees(): void
    {
        $this->actingAsRole('admin');

        $this->postJson("/api/professeurs/{$this->alice->id}/cours", ['courses' => []])->assertStatus(404)->assertJsonMissingPath('professeur');
        $this->getJson("/api/cours/{$this->mercredi->periodes()->first()->cours_id}/professeurs")->assertStatus(404);
    }
}
