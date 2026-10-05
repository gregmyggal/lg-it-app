<?php

namespace Tests\Feature;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\CourseSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Assignation professeur ⇄ classe : propagation aux sessions à venir (RG-8), conflits (RG-4), terminaison. */
class ClasseProfesseurAssignmentServiceTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private ClasseProfesseurAssignmentService $service;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->service = app(ClasseProfesseurAssignmentService::class);
        $this->importFwb($annee = $this->annee());
        $this->classe = $this->classeAvecSessions($annee);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nbLignes(Professeur $p): int
    {
        return SessionProfesseur::where('professeur_id', $p->id)->count();
    }

    public function test_classe_pas_commencee_assigne_les_14_sessions(): void
    {
        $alice = Professeur::factory()->create();

        $r = $this->service->assigner($this->classe, $alice, ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);

        $this->assertTrue($r['cree']);
        $this->assertSame(14, $r['recapitulatif']['sessions_assignees']);
        $this->assertSame(0, $r['recapitulatif']['sessions_passees_ignorees']);
        $this->assertSame(14, $this->nbLignes($alice));
        $this->assertSame(ProfesseurClasse::ROLE_PRINCIPAL, $r['assignation']->role);
    }

    public function test_classe_en_cours_propage_aussi_aux_seances_passees_orphelines(): void
    {
        $alice = Professeur::factory()->create();
        $cinquieme = $this->classe->sessions()->where('seance_numero', 5)->first();
        Carbon::setTestNow($cinquieme->date->copy()->addDay()->setTime(10, 0));

        $r = $this->service->assigner($this->classe, $alice);

        $this->assertSame(14, $r['recapitulatif']['sessions_assignees']);
        $this->assertSame(0, $r['recapitulatif']['sessions_passees_ignorees']);
        $this->assertSame(14, $this->nbLignes($alice));
        $this->assertSame($this->classe->sessions()->where('seance_numero', 1)->first()->date->toDateString(), $r['assignation']->date_debut->toDateString());
    }

    public function test_seances_passees_avec_professeur_ou_heures_ne_sont_pas_reprises(): void
    {
        $alice = Professeur::factory()->create();
        $bob = Professeur::factory()->create();
        $cinquieme = $this->classe->sessions()->where('seance_numero', 5)->first();
        Carbon::setTestNow($cinquieme->date->copy()->addDay()->setTime(10, 0));
        SessionProfesseur::factory()->create(['course_session_id' => $this->classe->sessions()->where('seance_numero', 1)->first()->id, 'professeur_id' => $bob->id]);
        Timesheet::create([
            'professeur_id' => $bob->id, 'date_prestation' => '2026-10-14', 'nombre_heures' => 2,
            'course_session_id' => $this->classe->sessions()->where('seance_numero', 2)->first()->id,
        ]);

        $r = $this->service->assigner($this->classe, $alice);

        $this->assertSame(12, $r['recapitulatif']['sessions_assignees']); // séances 3 à 14
        $this->assertSame(2, $r['recapitulatif']['sessions_passees_ignorees']);
    }

    public function test_date_de_debut_explicite_exclut_les_seances_passees(): void
    {
        $alice = Professeur::factory()->create();
        $cinquieme = $this->classe->sessions()->where('seance_numero', 5)->first();
        Carbon::setTestNow($cinquieme->date->copy()->addDay()->setTime(10, 0));

        $r = $this->service->assigner($this->classe, $alice, ['date_debut' => now()->toDateString()]);

        $this->assertSame(9, $r['recapitulatif']['sessions_assignees']);
        $this->assertSame(5, $r['recapitulatif']['sessions_passees_ignorees']);
    }

    public function test_assignation_idempotente(): void
    {
        $alice = Professeur::factory()->create();

        $this->service->assigner($this->classe, $alice);
        $r = $this->service->assigner($this->classe, $alice);

        $this->assertSame(0, $r['recapitulatif']['sessions_assignees']);
        $this->assertSame(14, $r['recapitulatif']['sessions_deja_assignees']);
        $this->assertSame(14, $this->nbLignes($alice));
        $this->assertSame(1, ProfesseurClasse::count());
    }

    public function test_sessions_annulees_exclues_de_la_propagation(): void
    {
        $alice = Professeur::factory()->create();
        $this->classe->sessions()->where('seance_numero', 3)->update(['statut' => CourseSession::STATUT_ANNULEE]);

        $r = $this->service->assigner($this->classe, $alice);

        $this->assertSame(13, $r['recapitulatif']['sessions_assignees']);
    }

    public function test_conflit_d_horaire_bloque_l_assignation(): void
    {
        $alice = Professeur::factory()->create();
        $autre = $this->classeAvecSessions($this->classe->anneeScolaire, ['heure_debut' => '15:00', 'heure_fin' => '18:00']);
        $this->service->assigner($autre, $alice);

        try {
            $this->service->assigner($this->classe, $alice);
            $this->fail('Un conflit d\'horaire aurait dû bloquer l\'assignation.');
        } catch (RegleMetierException $e) {
            $this->assertSame(422, $e->status);
        }

        $this->assertSame(0, ProfesseurClasse::where('classe_id', $this->classe->id)->count());
        $this->assertSame(14, $this->nbLignes($alice));
    }

    public function test_apercu_liste_les_conflits_sans_ecrire(): void
    {
        $alice = Professeur::factory()->create();
        $autre = $this->classeAvecSessions($this->classe->anneeScolaire, ['heure_debut' => '16:00', 'heure_fin' => '18:00']);
        $this->service->assigner($autre, $alice);

        $apercu = $this->service->apercu($this->classe, $alice);

        $this->assertCount(14, $apercu['conflits']);
        $this->assertSame(0, ProfesseurClasse::where('classe_id', $this->classe->id)->count());
    }

    public function test_terminer_retire_les_sessions_futures_sans_timesheet_et_conserve_l_historique(): void
    {
        $alice = Professeur::factory()->create();
        $this->service->assigner($this->classe, $alice);
        $sessions = $this->classe->sessions()->orderBy('date')->get();
        Timesheet::create([
            'professeur_id' => $alice->id,
            'course_session_id' => $sessions[9]->id,
            'date_prestation' => $sessions[9]->date->toDateString(),
            'nombre_heures' => 3,
        ]);
        Carbon::setTestNow($sessions[4]->date->copy()->setTime(10, 0));

        $r = $this->service->terminer($this->service->assignation($this->classe, $alice), $sessions[4]->date->toDateString());

        // sessions 6..14 (9 futures après la date de fin), dont la 10 avec timesheet conservée
        $this->assertSame(8, $r['recapitulatif']['sessions_retirees']);
        $this->assertSame(1, $r['recapitulatif']['sessions_conservees']);
        $this->assertSame(6, $this->nbLignes($alice));
        $this->assertNotNull($r['assignation']->date_fin);
        $this->assertDatabaseHas('professeur_classe', ['professeur_id' => $alice->id, 'classe_id' => $this->classe->id]);
    }

    public function test_reactivation_apres_terminaison(): void
    {
        $alice = Professeur::factory()->create();
        $this->service->assigner($this->classe, $alice);
        $this->service->terminer($this->service->assignation($this->classe, $alice), '2026-09-30');
        $this->assertSame(0, $this->nbLignes($alice));
        Carbon::setTestNow('2026-10-01 10:00:00'); // la fin de validité (30/09) est dépassée : assignation terminée

        $r = $this->service->assigner($this->classe, $alice);

        $this->assertTrue($r['cree']);
        $this->assertNull($r['assignation']->date_fin);
        $this->assertSame(14, $this->nbLignes($alice));
        $this->assertSame(1, ProfesseurClasse::count());
    }

    public function test_bis_herite_des_professeurs_actifs_de_la_classe(): void
    {
        $alice = Professeur::factory()->create();
        $this->service->assigner($this->classe, $alice);
        $annulee = $this->classe->sessions()->where('seance_numero', 5)->first();
        $annulee->update(['statut' => CourseSession::STATUT_ANNULEE, 'motif_annulation' => 'Test', 'cancelled_at' => now()]);

        $bis = app(CourseSessionService::class)->ajouterBis($this->classe, [
            'seance_numero' => 5,
            'date' => '2027-02-03',
        ], true);

        $this->assertTrue(SessionProfesseur::where('course_session_id', $bis->id)->where('professeur_id', $alice->id)->exists());
    }

    public function test_modifier_le_role_n_a_aucun_effet_sur_les_timesheets(): void
    {
        $alice = Professeur::factory()->create();
        $this->service->assigner($this->classe, $alice);
        $session = $this->classe->sessions()->first();
        $ts = Timesheet::create([
            'professeur_id' => $alice->id,
            'course_session_id' => $session->id,
            'date_prestation' => $session->date->toDateString(),
            'nombre_heures' => 3,
        ]);

        $this->service->modifier($this->service->assignation($this->classe, $alice), ['role' => ProfesseurClasse::ROLE_REMPLACANT]);

        $this->assertSame(ProfesseurClasse::ROLE_REMPLACANT, SessionProfesseur::where('professeur_id', $alice->id)->first()->role);
        $this->assertEquals(3, $ts->fresh()->nombre_heures);
        $this->assertSame($session->id, $ts->fresh()->course_session_id);
    }
}
