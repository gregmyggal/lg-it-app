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
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Remplacement ponctuel (RG-9) : sans contrainte de timesheet, jamais écrasé par la propagation. */
class SessionReplacementTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private SessionReplacementService $remplacements;

    private ClasseProfesseurAssignmentService $assignations;

    private Classe $classe;

    private Professeur $alice;

    private Professeur $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->remplacements = app(SessionReplacementService::class);
        $this->assignations = app(ClasseProfesseurAssignmentService::class);
        $this->classe = $this->classeAvecSessions($this->annee());
        $this->alice = Professeur::factory()->create();
        $this->carol = Professeur::factory()->create();
        $this->assignations->assigner($this->classe, $this->alice, ['role' => ProfesseurClasse::ROLE_PRINCIPAL]);
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

    public function test_remplacement_cree_la_ligne_du_remplacant_et_marque_le_remplace(): void
    {
        $s = $this->seance(5);

        $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);

        $a = SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->first();
        $c = SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->first();
        $this->assertTrue($a->remplace);
        $this->assertSame($this->carol->id, $a->remplace_par_professeur_id);
        $this->assertSame(SessionProfesseur::ORIGINE_REMPLACEMENT, $c->origine);
        $this->assertSame(ProfesseurClasse::ROLE_REMPLACANT, $c->role);
        // Les 13 autres sessions restent à Alice, sans Carol (AC-14)
        $this->assertSame(13, SessionProfesseur::where('professeur_id', $this->alice->id)->where('remplace', false)->count());
        $this->assertSame(1, SessionProfesseur::where('professeur_id', $this->carol->id)->count());
    }

    public function test_remplacement_sans_contrainte_de_timesheet_sur_session_passee(): void
    {
        $s = $this->seance(5);
        $ts = Timesheet::create([
            'professeur_id' => $this->alice->id,
            'course_session_id' => $s->id,
            'date_prestation' => $s->date->toDateString(),
            'nombre_heures' => 3,
            'statut_validation' => 'soumis',
        ]);
        Carbon::setTestNow($s->date->copy()->addDays(3)->setTime(10, 0)); // session passée

        $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);

        $this->assertTrue(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->value('remplace') == 1);
        $ts->refresh();
        $this->assertSame($this->alice->id, $ts->professeur_id);
        $this->assertSame($s->id, $ts->course_session_id);
        $this->assertSame('soumis', $ts->statut_validation);
        $this->assertSame(1, Timesheet::count());
    }

    public function test_remplacement_refuse_sur_session_annulee(): void
    {
        $s = $this->seance(5);
        $s->update(['statut' => CourseSession::STATUT_ANNULEE]);

        try {
            $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);
            $this->fail('Une session annulée ne peut pas être remplacée.');
        } catch (RegleMetierException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_remplacant_deja_assigne_est_refuse(): void
    {
        $s = $this->seance(5);
        $this->assignations->assigner($this->classe, $this->carol);

        try {
            $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);
            $this->fail('Le remplaçant déjà assigné doit être refusé.');
        } catch (RegleMetierException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_remplace_non_assigne_est_refuse(): void
    {
        try {
            $this->remplacements->remplacer($this->seance(5), $this->carol->id, Professeur::factory()->create()->id);
            $this->fail('Le remplacé doit être assigné à la session.');
        } catch (RegleMetierException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_annuler_le_remplacement_restaure_le_professeur(): void
    {
        $s = $this->seance(5);
        $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);

        $this->remplacements->annuler($s, $this->alice->id);

        $this->assertFalse((bool) SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->value('remplace'));
        $this->assertNull(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->value('remplace_par_professeur_id'));
        $this->assertFalse(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->exists());
    }

    public function test_annuler_sans_remplacement_est_un_conflit(): void
    {
        try {
            $this->remplacements->annuler($this->seance(5), $this->alice->id);
            $this->fail('Aucun remplacement à annuler.');
        } catch (RegleMetierException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_la_repropagation_n_ecrase_jamais_un_remplacement(): void
    {
        $s = $this->seance(5);
        $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);

        $this->assignations->assigner($this->classe, $this->alice); // idempotent
        $this->assignations->propagerAuxSessions($this->classe, $this->classe->sessions()->get());

        $a = SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->first();
        $this->assertTrue($a->remplace);
        $this->assertSame(1, SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->alice->id)->count());
        $this->assertSame(1, SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->count());
    }

    public function test_avertissement_non_bloquant_si_le_remplacant_est_deja_pris(): void
    {
        $s = $this->seance(5);
        $autre = $this->classeAvecSessions($this->classe->anneeScolaire, ['heure_debut' => '15:00', 'heure_fin' => '18:00']);
        $this->assignations->assigner($autre, $this->carol);

        $resultat = $this->remplacements->remplacer($s, $this->alice->id, $this->carol->id);

        $this->assertNotEmpty($resultat['avertissements']);
        $this->assertTrue(SessionProfesseur::where('course_session_id', $s->id)->where('professeur_id', $this->carol->id)->exists());
    }
}
