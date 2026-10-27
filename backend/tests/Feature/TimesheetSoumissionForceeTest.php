<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\TimesheetVersion;
use App\Models\User;
use App\Notifications\NotificationTimesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-05 : la direction ou l'admin soumet le mois d'un professeur en l'état (message obligatoire, version « par la direction »). */
class TimesheetSoumissionForceeTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    private Timesheet $a;

    private Timesheet $b;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->prof = Professeur::factory()->create();
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        $this->a = $this->ligne('2026-10-05', 2.0);
        $this->b = $this->ligne('2026-10-07', 1.5);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ligne(string $date, float $h, string $statut = 'brouillon'): Timesheet
    {
        return Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => 'animation', 'statut_validation' => $statut]);
    }

    private function forcer(array $donnees = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/professeurs/{$this->prof->id}/timesheets-mois/forcer-soumission", $donnees + ['annee' => 2026, 'mois' => 10, 'motif' => 'Échéance de paie du 5 novembre']);
    }

    private function codes(User $u): array
    {
        return $u->notifications()->get()->pluck('data.code')->all();
    }

    public function test_la_direction_soumet_le_mois_en_l_etat_avec_version_audit_et_notification(): void
    {
        $directeur = $this->actingAsRole('directeur');

        $r = $this->forcer()->assertOk();

        $this->assertSame(2, $r->json('lignes'));
        $this->assertEquals(3.5, $r->json('heures'));
        $this->assertEquals(70.0, $r->json('total_eur'));
        $this->assertSame(['soumis', 'soumis'], Timesheet::orderBy('id')->pluck('statut_validation')->all());
        $v = TimesheetVersion::firstOrFail();
        $this->assertSame(['soumise', 'direction', 1, $directeur->id], [$v->type, $v->origine, $v->numero, $v->auteur_id]);
        $audits = TimesheetAudit::where('action', 'soumission_forcee')->get();
        $this->assertCount(2, $audits);
        $this->assertSame('Échéance de paie du 5 novembre', $audits[0]->motif);
        $this->assertSame([NotificationTimesheet::SOUMISSION_FORCEE], $this->codes($this->prof->user));
        $this->assertSame([], $this->codes($directeur)); // la direction n'est pas prévenue de sa propre action
    }

    public function test_l_admin_peut_aussi_forcer(): void
    {
        $this->actingAsRole('admin');

        $this->forcer()->assertOk();

        $this->assertSame('direction', TimesheetVersion::firstOrFail()->origine);
    }

    public function test_le_message_est_obligatoire_et_rien_n_est_modifie_sinon(): void
    {
        $this->actingAsRole('directeur');

        $this->forcer(['motif' => ''])->assertStatus(422);
        $this->forcer(['motif' => 'ok'])->assertStatus(422);
        $this->forcer(['motif' => str_repeat('a', 501)])->assertStatus(422);

        $this->assertSame(['brouillon', 'brouillon'], Timesheet::orderBy('id')->pluck('statut_validation')->all());
        $this->assertSame(0, TimesheetVersion::count());
    }

    public function test_un_mois_deja_soumis_est_refuse_en_conflit(): void
    {
        $this->b->update(['statut_validation' => 'soumis']);
        $this->actingAsRole('directeur');

        $this->forcer()->assertStatus(409);

        $this->assertSame('brouillon', $this->a->fresh()->statut_validation);
        $this->assertSame(0, TimesheetVersion::count());
    }

    public function test_sans_heure_en_brouillon_la_demande_est_refusee(): void
    {
        Timesheet::query()->delete();
        $this->actingAsRole('directeur');

        $this->forcer()->assertStatus(422);
    }

    public function test_un_professeur_ne_peut_pas_forcer_et_les_anonymes_non_plus(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->forcer()->assertForbidden();
        $this->assertSame('brouillon', $this->a->fresh()->statut_validation);
    }

    public function test_anonyme_refuse(): void
    {
        $this->forcer()->assertUnauthorized();
    }

    public function test_le_detail_du_mois_expose_l_eligibilite_calculee_par_le_serveur(): void
    {
        $this->ligne('2026-10-09', 3.0); // 60 € ce jour : au-dessus du plafond journalier
        $this->actingAsRole('directeur');

        $d = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk()->json('soumission_forcee');

        $this->assertTrue($d['possible']);
        $this->assertNull($d['raison_impossible']);
        $this->assertSame(3, $d['lignes']);
        $this->assertEquals(6.5, $d['heures']);
        $this->assertSame(['2026-10-09'], $d['depassements']);
        $this->assertTrue($d['mois_termine']);

        $this->forcer()->assertOk();
        $apres = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->json('soumission_forcee');
        $this->assertFalse($apres['possible']);
        $this->assertSame('deja_soumis', $apres['raison_impossible']);
    }

    public function test_un_mois_en_cours_est_possible_mais_signale_non_termine(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->actingAsRole('directeur');

        $d = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->json('soumission_forcee');

        $this->assertTrue($d['possible']);
        $this->assertFalse($d['mois_termine']);
        $this->forcer()->assertOk();
    }

    public function test_aucune_heure_encodee_n_est_pas_eligible(): void
    {
        $this->actingAsRole('directeur');

        $d = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=9")->json('soumission_forcee');

        $this->assertFalse($d['possible']);
        $this->assertSame('aucune_heure', $d['raison_impossible']);
    }

    public function test_la_synthese_repere_le_brouillon_non_soumis(): void
    {
        $this->actingAsRole('directeur');

        $alertes = collect($this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertOk()->json('professeurs.0.alertes'));

        $this->assertSame('Brouillon non soumis · 3,5 h', $alertes->firstWhere('code', 'brouillon_non_soumis')['libelle']);
        $this->forcer()->assertOk();
        $this->assertNull(collect($this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->json('professeurs.0.alertes'))->firstWhere('code', 'brouillon_non_soumis'));
    }

    public function test_le_professeur_voit_que_la_direction_a_soumis_son_mois(): void
    {
        $directeur = $this->actingAsRole('directeur');
        $this->forcer()->assertOk();
        Sanctum::actingAs($this->prof->user);

        $r = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();

        $this->assertSame($directeur->name, $r->json('soumis_par_direction.auteur'));
        $this->assertSame('Échéance de paie du 5 novembre', $r->json('soumis_par_direction.message'));
        $this->assertEquals(3.5, $r->json('soumis_par_direction.heures'));
        $this->assertTrue($r->json('mois_soumis'));
    }

    public function test_la_soumission_par_le_professeur_reste_inchangee(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();

        $this->assertSame('professeur', TimesheetVersion::firstOrFail()->origine);
        $this->assertSame(0, TimesheetAudit::where('action', 'soumission_forcee')->count());
        $this->assertNull($this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->json('soumis_par_direction'));
    }

    public function test_les_versions_exposent_l_origine_et_le_circuit_continue(): void
    {
        $this->actingAsRole('directeur');
        $this->forcer()->assertOk();

        $r = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertOk();
        $this->assertSame('direction', $r->json('soumise.origine'));

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();
        $this->assertSame(['confirme', 'confirme'], Timesheet::orderBy('id')->pluck('statut_validation')->all());
        $this->assertSame(1, TimesheetVersion::where('type', 'validee')->count());
    }
}
