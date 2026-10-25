<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Services\ClasseProfesseurAssignmentService;
use App\Services\SessionReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** « Aujourd'hui » du professeur (UI-M3) : GET /api/mon-accueil. */
class MonAccueilTest extends TestCase
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
        ProfesseurTarif::create(['professeur_id' => $this->alice->id, 'tarif_horaire_eur' => 10, 'date_debut' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seance(int $n): CourseSession
    {
        return $this->classe->sessions()->where('seance_numero', $n)->where('bis_rang', 0)->firstOrFail();
    }

    /** Se place à l'heure donnée (UTC) le jour de la séance ; l'application raisonne en Europe/Brussels. */
    private function le(int $n, string $heure): void
    {
        Carbon::setTestNow($this->seance($n)->date->copy()->setTimeFromTimeString($heure));
    }

    public function test_a_encoder_liste_les_seances_commencees_du_mois_la_plus_recente_d_abord(): void
    {
        $this->le(2, '20:00');
        Sanctum::actingAs($this->alice->user);

        $r = $this->getJson('/api/mon-accueil')->assertOk();

        $ids = collect($r->json('a_encoder'))->pluck('id')->all();
        $this->assertSame([$this->seance(2)->id, $this->seance(1)->id], array_slice($ids, 0, 2));
        $this->assertNotContains($this->seance(3)->id, $ids); // pas encore commencée
        $this->assertEquals(2, $r->json('a_encoder.0.duree_par_defaut')); // heures défrayables, pas la durée du calendrier
        $this->assertFalse($r->json('mois_soumis'));
    }

    public function test_prochaine_seance_en_cours_puis_suivante(): void
    {
        Sanctum::actingAs($this->alice->user);

        $this->le(2, '13:00'); // 15:00 à Bruxelles (UTC+2) : séance 14h–17h en cours
        $r = $this->getJson('/api/mon-accueil')->assertOk();
        $this->assertSame($this->seance(2)->id, $r->json('prochaine_seance.id'));
        $this->assertTrue($r->json('prochaine_seance.en_cours'));
        $this->assertTrue($r->json('prochaine_seance.est_aujourdhui'));
        $this->assertSame([$this->bob->prenom.' '.$this->bob->nom], $r->json('prochaine_seance.co_professeurs'));

        $this->le(2, '20:00'); // terminée : on passe à la suivante
        $r = $this->getJson('/api/mon-accueil')->assertOk();
        $this->assertSame($this->seance(3)->id, $r->json('prochaine_seance.id'));
        $this->assertFalse($r->json('prochaine_seance.en_cours'));
    }

    public function test_seance_annulee_ignoree(): void
    {
        $this->le(2, '20:00');
        $this->seance(3)->update(['statut' => CourseSession::STATUT_ANNULEE]);
        Sanctum::actingAs($this->alice->user);

        $r = $this->getJson('/api/mon-accueil')->assertOk();

        $this->assertSame($this->seance(4)->id, $r->json('prochaine_seance.id'));
    }

    public function test_remplacant_voit_qui_il_remplace_et_le_remplace_ne_voit_plus_la_seance(): void
    {
        $this->le(2, '12:00');
        $carol = Professeur::factory()->create();
        app(SessionReplacementService::class)->remplacer($this->seance(2), $this->alice->id, $carol->id);

        Sanctum::actingAs($carol->user);
        $r = $this->getJson('/api/mon-accueil')->assertOk();
        $this->assertSame($this->seance(2)->id, $r->json('prochaine_seance.id'));
        $this->assertSame($this->alice->prenom.' '.$this->alice->nom, $r->json('prochaine_seance.remplace'));

        Sanctum::actingAs($this->alice->user);
        $r = $this->getJson('/api/mon-accueil')->assertOk();
        $this->assertNotSame($this->seance(2)->id, $r->json('prochaine_seance.id'));
    }

    public function test_seance_encodee_sort_de_la_pile_et_la_progression_avance(): void
    {
        $this->le(2, '20:00');
        Sanctum::actingAs($this->alice->user);
        $avant = $this->getJson('/api/mon-accueil')->assertOk();

        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id, 'nombre_heures' => 2])->assertCreated();
        $apres = $this->getJson('/api/mon-accueil')->assertOk();

        $this->assertNotContains($this->seance(1)->id, collect($apres->json('a_encoder'))->pluck('id')->all());
        $this->assertSame($avant->json('progression.seances_encodees') + 1, $apres->json('progression.seances_encodees'));
        $this->assertEquals(2.0, $apres->json('progression.heures'));
        $this->assertEquals(20.0, $apres->json('progression.montant')); // 2 h × 10 €

        // Cohérence avec « Mon mois »
        $mois = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();
        $this->assertEquals($mois->json('synthese.heures'), $apres->json('progression.heures'));
        $this->assertEquals($mois->json('synthese.montant'), $apres->json('progression.montant'));
    }

    public function test_mois_soumis_vide_la_pile(): void
    {
        $this->le(2, '20:00');
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertCreated();
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();

        $r = $this->getJson('/api/mon-accueil')->assertOk();

        $this->assertTrue($r->json('mois_soumis'));
        $this->assertSame([], $r->json('a_encoder'));
    }

    public function test_isolation_les_heures_d_un_autre_professeur_ne_sont_pas_visibles(): void
    {
        $this->le(2, '20:00');
        Sanctum::actingAs($this->bob->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertCreated();

        Sanctum::actingAs($this->alice->user);
        $r = $this->getJson('/api/mon-accueil')->assertOk();

        $this->assertEquals(0.0, $r->json('progression.heures'));
        $this->assertContains($this->seance(1)->id, collect($r->json('a_encoder'))->pluck('id')->all());
    }

    public function test_staff_refuse_et_sans_jeton_401(): void
    {
        $this->getJson('/api/mon-accueil')->assertUnauthorized();

        $this->actingAsRole('directeur');
        $this->getJson('/api/mon-accueil')->assertForbidden();

        $this->actingAsRole('admin');
        $this->getJson('/api/mon-accueil')->assertForbidden();
    }
}
