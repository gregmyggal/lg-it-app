<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** CLS-04 : ajout ponctuel d'un professeur à une session (même passée), retrait tant qu'aucune heure n'est encodée. */
class SessionAjoutProfesseurTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Classe $classe;

    private Professeur $alice;

    private Professeur $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        $this->carol = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($this->classe, $this->alice, ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(int $numero): CourseSession
    {
        return $this->classe->sessions()->where('seance_numero', $numero)->where('bis_rang', 0)->firstOrFail();
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_ajout_sur_session_passee_avec_role_principal_par_defaut_sans_toucher_aux_timesheets(): void
    {
        $s = $this->seance(5);
        $ts = Timesheet::create([
            'professeur_id' => $this->alice->id, 'course_session_id' => $s->id, 'date_prestation' => $s->date->toDateString(),
            'nombre_heures' => 3, 'statut_validation' => 'soumis',
        ]);
        Carbon::setTestNow($s->date->copy()->addDays(3)->setTime(10, 0));
        Sanctum::actingAs($this->staff());

        $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->carol->id])
            ->assertCreated()->assertJsonPath('avertissements', []);

        $ligne = SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->firstOrFail();
        $this->assertSame(SessionProfesseur::ORIGINE_AJOUT, $ligne->origine);
        $this->assertSame(ProfesseurClasse::ROLE_PRINCIPAL, $ligne->role);
        $this->assertFalse($ligne->remplace);
        $this->assertSame(2, SessionProfesseur::where('course_session_id', $s->id)->count());
        $this->assertSame('soumis', $ts->fresh()->statut_validation);
        $this->assertSame(1, Timesheet::count());
    }

    public function test_session_annulee_409_et_doublon_422(): void
    {
        Sanctum::actingAs($this->staff());
        $annulee = $this->seance(6);
        $annulee->update(['statut' => CourseSession::STATUT_ANNULEE]);

        $this->postJson("/api/sessions/{$annulee->id}/professeurs", ['professeur_id' => $this->carol->id])->assertStatus(409);
        $this->postJson('/api/sessions/'.$this->seance(5)->id.'/professeurs', ['professeur_id' => $this->alice->id])
            ->assertStatus(422)->assertJsonValidationErrors('professeur_id');
    }

    public function test_professeur_remplace_ne_peut_pas_etre_ajoute(): void
    {
        $s = $this->seance(5);
        app(SessionReplacementService::class)->remplacer($s, $this->alice->id, $this->carol->id);
        Sanctum::actingAs($this->staff());

        $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->alice->id])->assertStatus(422);
    }

    public function test_professeur_ne_peut_pas_ajouter_ni_retirer(): void
    {
        $s = $this->seance(5);
        Sanctum::actingAs($this->alice->user);

        $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->carol->id])->assertForbidden();
        $this->deleteJson("/api/sessions/{$s->id}/professeurs/{$this->alice->id}")->assertForbidden();
    }

    public function test_conflit_d_horaire_est_un_avertissement_non_bloquant(): void
    {
        $autre = $this->classeAvecSessions($this->classe->anneeScolaire, ['heure_debut' => '15:00', 'heure_fin' => '18:00']);
        app(ClasseProfesseurAssignmentService::class)->assigner($autre, $this->carol);
        $s = $this->seance(5);
        Sanctum::actingAs($this->staff());

        $res = $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->carol->id])->assertCreated();
        $this->assertNotEmpty($res->json('avertissements'));
    }

    public function test_retrait_d_un_ajout_sans_timesheet(): void
    {
        $s = $this->seance(5);
        Sanctum::actingAs($this->staff());
        $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->carol->id])->assertCreated();

        $this->deleteJson("/api/sessions/{$s->id}/professeurs/{$this->carol->id}")->assertOk();
        $this->assertFalse(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->exists());
    }

    public function test_retrait_refuse_si_timesheet_deja_encodee(): void
    {
        $s = $this->seance(5);
        Sanctum::actingAs($this->staff());
        $this->postJson("/api/sessions/{$s->id}/professeurs", ['professeur_id' => $this->carol->id])->assertCreated();
        Timesheet::create([
            'professeur_id' => $this->carol->id, 'course_session_id' => $s->id, 'date_prestation' => $s->date->toDateString(),
            'nombre_heures' => 3, 'statut_validation' => 'brouillon',
        ]);

        $this->deleteJson("/api/sessions/{$s->id}/professeurs/{$this->carol->id}")->assertStatus(409);
        $this->assertTrue(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->exists());
    }

    public function test_retrait_refuse_pour_une_ligne_de_classe(): void
    {
        Sanctum::actingAs($this->staff());

        $this->deleteJson('/api/sessions/'.$this->seance(5)->id."/professeurs/{$this->alice->id}")->assertStatus(409);
    }

    public function test_un_ajout_survit_a_la_terminaison_de_l_assignation_de_classe(): void
    {
        $service = app(ClasseProfesseurAssignmentService::class);
        $s = $this->seance(9);
        app(SessionReplacementService::class)->ajouter($s, $this->carol->id);
        $assignation = $service->assigner($this->classe, $this->carol)['data'] ?? null;
        $service->terminer(ProfesseurClasse::where('professeur_id', $this->carol->id)->firstOrFail());

        $this->assertSame(SessionProfesseur::ORIGINE_AJOUT, SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->value('origine'));
    }

    public function test_le_professeur_ajoute_voit_la_session_dans_mes_classes(): void
    {
        $s = $this->seance(9);
        app(SessionReplacementService::class)->ajouter($s, $this->carol->id);
        Sanctum::actingAs($this->carol->user);

        $this->getJson('/api/mes-classes')->assertOk()->assertJsonPath('remplacements.0.id', $s->id);
        $res = $this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertOk();
        $this->assertSame('ajoute', collect($res->json('data'))->firstWhere('id', $s->id)['ma_situation']['type']);
    }
}
