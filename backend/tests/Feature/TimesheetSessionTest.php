<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Encodage lié à une session ou libre (CLS-01 T3 : R-T3-1 à 5, AC-3, AC-25, AC-27 à AC-30). */
class TimesheetSessionTest extends TestCase
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
        $service->assigner($this->classe, $this->alice);
        $service->assigner($this->classe, $this->bob);
        // 11/11/2026 : après la séance 4 (mercredi 04/11) du calendrier de tests ; on se place après la séance 3.
        $this->allerApres(3);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function allerApres(int $seance): CourseSession
    {
        $s = $this->classe->sessions()->where('seance_numero', $seance)->where('bis_rang', 0)->firstOrFail();
        Carbon::setTestNow($s->date->copy()->setTime(20, 0));

        return $s;
    }

    private function seance(int $n): CourseSession
    {
        return $this->classe->sessions()->where('seance_numero', $n)->where('bis_rang', 0)->firstOrFail();
    }

    public function test_session_commencee_deduit_date_cours_et_duree(): void
    {
        $s = $this->seance(2);
        Sanctum::actingAs($this->alice->user);

        $r = $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertCreated();

        $r->assertJsonPath('professeur_id', $this->alice->id)
            ->assertJsonPath('course_session_id', $s->id)
            ->assertJsonPath('cours_id', $this->classe->periodes()->first()->cours_id)
            ->assertJsonPath('type_activite', 'animation')
            ->assertJsonPath('statut_validation', 'brouillon')
            ->assertJsonPath('session.libelle', 'Séance 2');
        $this->assertSame($s->date->toDateString(), substr($r->json('date_prestation'), 0, 10));
        $this->assertEquals(2, $r->json('nombre_heures')); // heures défrayables (14h–17h au calendrier)
    }

    public function test_professeur_id_du_client_est_ignore(): void
    {
        Sanctum::actingAs($this->alice->user);

        $r = $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(2)->id, 'professeur_id' => $this->bob->id])->assertCreated();

        $this->assertSame($this->alice->id, $r->json('professeur_id'));
    }

    public function test_deux_professeurs_ont_chacun_leur_saisie_independante_ac3(): void
    {
        $s = $this->seance(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'nombre_heures' => 3])->assertCreated();
        Sanctum::actingAs($this->bob->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'nombre_heures' => 2.5])->assertCreated();

        $this->assertSame(2, Timesheet::where('course_session_id', $s->id)->count());
        $this->assertEquals(3, Timesheet::where('professeur_id', $this->alice->id)->value('nombre_heures'));
        $this->assertEquals(2.5, Timesheet::where('professeur_id', $this->bob->id)->value('nombre_heures'));
    }

    public function test_doublon_refuse_mais_autre_type_accepte_ac28(): void
    {
        $s = $this->seance(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertCreated();

        $this->postJson('/api/timesheets', ['course_session_id' => $s->id])
            ->assertStatus(422)->assertJsonPath('message', 'Ces heures sont déjà encodées pour cette session.');
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'type_activite' => 'preparation', 'nombre_heures' => 1])
            ->assertCreated();
    }

    public function test_session_non_commencee_ou_annulee_refusee_ac29(): void
    {
        Sanctum::actingAs($this->alice->user);

        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(8)->id])->assertStatus(422);

        $annulee = $this->seance(2);
        $annulee->update(['statut' => CourseSession::STATUT_ANNULEE]);
        $this->postJson('/api/timesheets', ['course_session_id' => $annulee->id])->assertStatus(422);
        $this->assertSame(0, Timesheet::count());
    }

    public function test_le_remplace_ne_peut_plus_creer_mais_garde_ses_saisies_et_le_remplacant_encode_ac25_ac27(): void
    {
        $s = $this->seance(2);
        $carol = Professeur::factory()->create();
        Sanctum::actingAs($this->alice->user);
        $ts = $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertCreated()->json('id');
        $avant = Timesheet::find($ts)->toArray();

        app(SessionReplacementService::class)->remplacer($s, $this->alice->id, $carol->id);

        $this->assertSame($avant, Timesheet::find($ts)->toArray()); // AC-25 : aucune timesheet touchée
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'type_activite' => 'preparation', 'nombre_heures' => 1])->assertForbidden();
        $this->getJson('/api/timesheets')->assertOk()->assertJsonCount(1); // il garde ses saisies
        $this->putJson("/api/timesheets/{$ts}", ['nombre_heures' => 2])->assertOk(); // et leur workflow (brouillon)

        Sanctum::actingAs($carol->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertCreated();
        $this->assertSame(2, Timesheet::where('course_session_id', $s->id)->count());
    }

    public function test_professeur_non_assigne_ou_staff_ne_peut_pas_encoder(): void
    {
        $s = $this->seance(2);

        Sanctum::actingAs(Professeur::factory()->create()->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertForbidden();

        $this->actingAsRole('directeur');
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->assertForbidden();
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-11-02', 'type_activite' => 'preparation', 'nombre_heures' => 1])->assertForbidden();
    }

    public function test_encodage_libre_sans_cours_ni_session_ac30(): void
    {
        Sanctum::actingAs($this->alice->user);

        $this->postJson('/api/timesheets', ['date_prestation' => '2026-11-02', 'type_activite' => 'preparation', 'nombre_heures' => 2])
            ->assertCreated()->assertJsonPath('course_session_id', null)->assertJsonPath('cours_id', null)->assertJsonPath('session', null);
        // Plusieurs saisies libres du même type le même jour sont permises (aucune unicité sans session)
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-11-02', 'type_activite' => 'preparation', 'nombre_heures' => 1])->assertCreated();
        $this->postJson('/api/timesheets', ['type_activite' => 'preparation'])->assertStatus(422)->assertJsonValidationErrors(['nombre_heures', 'date_prestation']);
    }

    public function test_session_liee_non_modifiable_par_update_et_modifiable_en_brouillon(): void
    {
        $s = $this->seance(2);
        Sanctum::actingAs($this->alice->user);
        $id = $this->postJson('/api/timesheets', ['course_session_id' => $s->id])->json('id');

        $this->putJson("/api/timesheets/{$id}", ['nombre_heures' => 2, 'date_prestation' => '2026-01-01'])->assertOk();

        $t = Timesheet::find($id);
        $this->assertEquals(2, $t->nombre_heures);
        $this->assertSame($s->date->toDateString(), $t->date_prestation->toDateString()); // date de la session conservée
    }

    public function test_un_professeur_ne_voit_que_ses_saisies_et_ses_euros_et_les_filtres_staff(): void
    {
        ProfesseurTarif::create(['professeur_id' => $this->alice->id, 'tarif_horaire_eur' => 12, 'date_debut' => '2026-01-01']);
        $s = $this->seance(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'nombre_heures' => 3]);
        Sanctum::actingAs($this->bob->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $s->id, 'nombre_heures' => 3]);

        Sanctum::actingAs($this->alice->user);
        $liste = $this->getJson('/api/timesheets')->assertOk()->assertJsonCount(1)->json();
        $this->assertSame($this->alice->id, $liste[0]['professeur_id']);
        $this->assertEquals(36.0, $liste[0]['montant_brut']); // Q22 : ses propres euros
        $this->assertTrue($liste[0]['can']['update']);

        $this->actingAsRole('directeur');
        $this->getJson('/api/timesheets')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/timesheets?classe_id={$this->classe->id}&professeur_id={$this->bob->id}")->assertOk()->assertJsonCount(1);
        $this->getJson('/api/timesheets?classe_id=999999')->assertOk()->assertJsonCount(0);
    }
}
