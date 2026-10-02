<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-01 T4 : reconfirmation (signer / contester), traitement par la direction, notifications. */
class TimesheetConfirmationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prof = Professeur::factory()->create();
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
    }

    private function ligne(string $statut, string $date = '2026-10-10', bool $signee = false, float $h = 1): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => 'animation',
            'statut_validation' => $statut, 'signature_professeur' => $signee ? now() : null,
        ]);
    }

    private function enProf(): void
    {
        Sanctum::actingAs($this->prof->user);
    }

    private function p(array $extra = []): array
    {
        return ['annee' => 2026, 'mois' => 10] + $extra;
    }

    public function test_le_professeur_conteste_ses_saisies_confirmees_non_signees_et_la_direction_est_prevenue(): void
    {
        $directeur = User::factory()->directeur()->create();
        $a = $this->ligne('confirme');
        $signee = $this->ligne('confirme', '2026-10-11', true);
        $this->enProf();

        $this->postJson('/api/timesheets/contester-mois', $this->p(['motif' => 'Il manque une heure']))->assertOk()->assertJsonPath('contestees', 1);

        $this->assertSame('conteste', $a->fresh()->statut_validation);
        $this->assertSame('confirme', $signee->fresh()->statut_validation); // déjà signée : intacte
        $this->assertSame('Il manque une heure', TimesheetAudit::where('action', 'contestation')->firstOrFail()->motif);
        $this->assertSame(1, $directeur->notifications()->count());
        $this->assertSame('contestation', $directeur->notifications->first()->data['code']);
    }

    public function test_on_ne_conteste_pas_sans_saisie_confirmee_non_signee_ni_sans_motif(): void
    {
        $this->ligne('soumis');
        $this->enProf();

        $this->postJson('/api/timesheets/contester-mois', $this->p(['motif' => 'abc']))->assertStatus(422);
        $this->postJson('/api/timesheets/contester-mois', $this->p())->assertStatus(422)->assertJsonValidationErrors('motif');
    }

    public function test_une_contestation_bloque_la_signature_et_le_statut_du_mois_est_conteste(): void
    {
        $this->ligne('conteste');
        $this->enProf();

        $this->postJson('/api/timesheets/sign-month', ['professeur_id' => $this->prof->id, 'year' => 2026, 'month' => 10])
            ->assertStatus(422)->assertJsonPath('error', 'Contestation en cours : en attente de la direction');

        $this->actingAsRole('directeur');
        $j = $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertOk()->json();
        $this->assertSame('conteste', $j['professeurs'][0]['statut_mois']);
        $this->assertSame(1, $j['kpis']['conteste']);
    }

    public function test_la_direction_traite_la_contestation_et_le_professeur_est_prevenu(): void
    {
        $t = $this->ligne('conteste', '2026-10-10', true);
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->prof->id}/timesheets-mois/traiter-contestation", $this->p(['reponse' => 'Heure ajoutée']))
            ->assertOk()->assertJsonPath('traitees', 1);

        $this->assertSame('soumis', $t->fresh()->statut_validation);
        $this->assertNull($t->fresh()->signature_professeur);
        $this->assertSame('contestation_traitee', $this->prof->user->notifications()->firstOrFail()->data['code']);
    }

    public function test_seul_le_staff_traite_une_contestation(): void
    {
        $this->ligne('conteste');
        $this->enProf();

        $this->postJson("/api/professeurs/{$this->prof->id}/timesheets-mois/traiter-contestation", $this->p(['reponse' => 'ok ok']))->assertForbidden();
    }

    public function test_apres_ajustement_d_une_ligne_signee_le_professeur_peut_signer_le_reste_est_deja_signe(): void
    {
        $this->ligne('confirme', '2026-10-10', true);
        $b = $this->ligne('confirme', '2026-10-11', true);
        $this->actingAsRole('directeur');
        $this->postJson("/api/timesheets/{$b->id}/adapter", ['nombre_heures' => 2, 'motif' => 'correction'])->assertOk();

        $this->enProf();
        $etat = $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->json();
        $this->assertTrue($etat['peut_signer']);
        $this->assertSame('attente_prof', $etat['statut_mois']);
        $this->assertCount(1, $etat['ajustements']);

        $this->postJson('/api/timesheets/sign-month', ['professeur_id' => $this->prof->id, 'year' => 2026, 'month' => 10])->assertOk();
        $this->assertSame(0, Timesheet::whereNull('signature_professeur')->count());
    }

    public function test_le_professeur_est_notifie_une_seule_fois_tant_que_non_lu(): void
    {
        $a = $this->ligne('confirme', '2026-10-10', true);
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$a->id}/adapter", ['nombre_heures' => 2, 'motif' => 'première'])->assertOk();
        $this->postJson("/api/timesheets/{$a->id}/adapter", ['nombre_heures' => 3, 'motif' => 'seconde'])->assertOk();

        $this->assertSame(1, $this->prof->user->notifications()->count());
        $this->assertSame('mois_a_confirmer', $this->prof->user->notifications->first()->data['code']);
    }

    public function test_la_validation_en_lot_previent_le_professeur(): void
    {
        $t = $this->ligne('soumis');
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$t->id]])->assertOk();

        $this->assertSame(1, $this->prof->user->notifications()->count());
    }

    public function test_la_cloche_est_privee_marquer_lue_et_toutes_lues(): void
    {
        $a = $this->ligne('confirme', '2026-10-10', true);
        $this->actingAsRole('directeur');
        $this->postJson("/api/timesheets/{$a->id}/adapter", ['nombre_heures' => 2, 'motif' => 'abc'])->assertOk();
        $notif = $this->prof->user->notifications()->firstOrFail();

        $autre = Professeur::factory()->create();
        Sanctum::actingAs($autre->user);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('non_lues', 0);
        $this->postJson("/api/notifications/{$notif->id}/lue")->assertNotFound();

        $this->enProf();
        $this->getJson('/api/notifications')->assertJsonPath('non_lues', 1)->assertJsonPath('data.0.url', '/timesheets');
        $this->postJson("/api/notifications/{$notif->id}/lue")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('non_lues', 0);
    }
}
