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

/** « Encoder mon mois » (CLS-01 T3, AC-31) et état d'encodage dans « Mes classes ». */
class TimesheetMoisTest extends TestCase
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

    /** Se place le soir de la séance donnée. */
    private function soirDe(int $n): void
    {
        Carbon::setTestNow($this->seance($n)->date->copy()->setTime(20, 0));
    }

    public function test_mon_mois_liste_toutes_les_seances_passees_et_a_venir_ts03(): void
    {
        // Mercredis du calendrier de tests : 07/10, 14/10, 04/11, 18/11, 25/11… (vacances d'automne sautées)
        $this->soirDe(1); // 07/10 au soir : séance 1 commencée, séance 2 (14/10) à venir
        Sanctum::actingAs($this->alice->user);

        $r = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();

        $sessions = collect($r->json('sessions'));
        $this->assertSame([1, 2, 3, 4], $sessions->pluck('seance_numero')->all());
        $this->assertSame(['a_encoder', 'a_encoder', 'a_encoder', 'a_encoder'], $sessions->pluck('encodage')->all());
        $this->assertSame([false, true, true, true], $sessions->pluck('est_a_venir')->all());
        $this->assertTrue($sessions[1]['peut_encoder']); // à venir : encodable à l'avance
        $this->assertEquals(2, $sessions[0]['duree_par_defaut']); // heures défrayables (séance de 3 h au calendrier)
        $this->assertSame(0, $r->json('synthese.nb_brouillons'));
    }

    public function test_etat_encodage_synthese_euros_et_saisies_libres(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertCreated();
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-05', 'type_activite' => 'preparation', 'nombre_heures' => 2])->assertCreated();

        $r = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();

        $this->assertSame('brouillon', collect($r->json('sessions'))->firstWhere('seance_numero', 1)['encodage']);
        $this->assertSame('a_encoder', collect($r->json('sessions'))->firstWhere('seance_numero', 2)['encodage']);
        $this->assertCount(1, $r->json('libres'));
        $this->assertEquals(4.0, $r->json('synthese.heures')); // séance 1 : 2 h défrayables + 2 h de préparation
        $this->assertEquals(40.0, $r->json('synthese.montant')); // Q22 : ses euros (4 h × 10 €)
        $this->assertSame(2, $r->json('synthese.jours'));
        $this->assertSame(2, $r->json('synthese.nb_brouillons'));
        $this->assertFalse($r->json('peut_signer'));
    }

    public function test_isolation_un_professeur_ne_voit_jamais_les_heures_d_un_autre(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->bob->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertCreated();

        Sanctum::actingAs($this->alice->user);
        $r = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();

        $this->assertSame(0.0, (float) $r->json('synthese.heures'));
        $this->assertSame('a_encoder', collect($r->json('sessions'))->firstWhere('seance_numero', 1)['encodage']);
        $this->assertSame([], collect($r->json('sessions'))->flatMap(fn ($s) => $s['mes_timesheets'])->all());
    }

    public function test_remplace_voit_la_session_remplacee_avec_ses_heures_et_le_remplacant(): void
    {
        $this->soirDe(2);
        $carol = Professeur::factory()->create();
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(2)->id])->assertCreated();
        app(SessionReplacementService::class)->remplacer($this->seance(2), $this->alice->id, $carol->id);

        $s = collect($this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk()->json('sessions'))->firstWhere('seance_numero', 2);

        $this->assertSame('brouillon', $s['encodage']);
        $this->assertFalse($s['peut_encoder']);
        $this->assertSame($carol->id, $s['remplace_par']['id']);
        $this->assertCount(1, $s['mes_timesheets']);
    }

    public function test_session_annulee_marquee_et_non_encodable(): void
    {
        $this->soirDe(2);
        $this->seance(2)->update(['statut' => CourseSession::STATUT_ANNULEE]);
        Sanctum::actingAs($this->alice->user);

        $s = collect($this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->json('sessions'))->firstWhere('seance_numero', 2);

        $this->assertTrue($s['annulee']);
        $this->assertFalse($s['peut_encoder']);
    }

    public function test_soumettre_le_mois_soumet_toutes_les_heures_encodees_sans_cocher_ac31(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-05', 'type_activite' => 'preparation', 'nombre_heures' => 2])->assertCreated();
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertCreated();

        $r = $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();

        $this->assertSame(2, $r->json('soumises'));
        $this->assertSame(2, Timesheet::where('professeur_id', $this->alice->id)->where('statut_validation', 'soumis')->count());
    }

    public function test_soumettre_un_mois_sans_heure_encodee_est_refuse(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);

        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertStatus(422);
        $this->assertSame(0, Timesheet::count());
    }

    public function test_un_mois_ne_se_soumet_qu_une_fois_et_plus_aucune_saisie_n_y_est_ajoutee(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-05', 'type_activite' => 'preparation', 'nombre_heures' => 2])->assertCreated();
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();

        $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk()->assertJsonPath('mois_soumis', true);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertStatus(422);
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-20', 'type_activite' => 'preparation', 'nombre_heures' => 1])->assertStatus(422);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id])->assertStatus(422);

        $this->assertSame(1, Timesheet::count());
    }

    public function test_il_n_existe_pas_de_soumission_ligne_par_ligne(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);
        $id = $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-05', 'type_activite' => 'preparation', 'nombre_heures' => 2])->assertCreated()->json('id');

        $this->postJson("/api/timesheets/{$id}/submit")->assertNotFound();
        $this->assertSame('brouillon', Timesheet::find($id)->statut_validation);
    }

    public function test_staff_ne_peut_pas_utiliser_l_ecran_mensuel(): void
    {
        $this->actingAsRole('directeur');

        $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertForbidden();
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertForbidden();
    }

    public function test_mes_classes_sessions_expose_l_etat_d_encodage(): void
    {
        $this->soirDe(2);
        Sanctum::actingAs($this->alice->user);
        $this->postJson('/api/timesheets', ['course_session_id' => $this->seance(1)->id, 'nombre_heures' => 3])->assertCreated();

        $sessions = collect($this->getJson("/api/mes-classes/{$this->classe->id}/sessions")->assertOk()->json('data'))->keyBy('seance_numero');

        $this->assertSame('brouillon', $sessions[1]['encodage']);
        $this->assertCount(1, $sessions[1]['mes_timesheets']);
        $this->assertSame('a_encoder', $sessions[2]['encodage']);
        $this->assertTrue($sessions[2]['peut_encoder']);
        $this->assertTrue($sessions[5]['peut_encoder']); // TS-03 : une séance à venir est encodable
        $this->assertTrue($sessions[5]['est_a_venir']);
        $this->assertEquals(2, $sessions[2]['duree_par_defaut']);
    }
}
