<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-01 T1 : adaptation d'une saisie par le staff, motif obligatoire, historique. */
class TimesheetAdaptationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private function saisie(string $statut = 'soumis', array $attrs = []): Timesheet
    {
        return Timesheet::create($attrs + [
            'professeur_id' => Professeur::factory()->create()->id,
            'date_prestation' => '2026-10-10', 'nombre_heures' => 1, 'type_activite' => 'preparation',
            'statut_validation' => $statut,
        ]);
    }

    public function test_le_directeur_adapte_les_heures_avec_motif_et_une_trace_est_creee(): void
    {
        $t = $this->saisie();
        $user = $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 2, 'motif' => 'Réunion confirmée'])
            ->assertOk()
            ->assertJsonPath('nombre_heures', '2.00');

        $audit = TimesheetAudit::firstOrFail();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertEquals(1.0, $audit->avant['nombre_heures']);
        $this->assertEquals(2.0, $audit->apres['nombre_heures']);
        $this->assertSame('Réunion confirmée', $audit->motif);

        $this->getJson("/api/timesheets/{$t->id}/historique")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.motif', 'Réunion confirmée');
    }

    public function test_le_motif_est_obligatoire(): void
    {
        $t = $this->saisie();
        $this->actingAsRole('admin');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 2])->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->assertSame(0, TimesheetAudit::count());
    }

    public function test_une_adaptation_sans_changement_est_refusee(): void
    {
        $t = $this->saisie();
        $this->actingAsRole('admin');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 1, 'motif' => 'rien'])->assertStatus(422);
    }

    public function test_adapter_une_saisie_confirmee_retire_la_signature_du_professeur(): void
    {
        $t = $this->saisie('confirme', ['signature_professeur' => now()]);
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['type_activite' => 'animation', 'motif' => 'Type corrigé'])->assertOk();

        $this->assertNull($t->fresh()->signature_professeur);
        $this->assertSame('animation', $t->fresh()->type_activite);
    }

    public function test_brouillon_et_genere_ne_sont_pas_adaptables(): void
    {
        $this->actingAsRole('admin');
        foreach (['brouillon', 'genere'] as $statut) {
            $t = $this->saisie($statut);
            $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 2, 'motif' => 'test'])->assertForbidden();
        }
    }

    public function test_la_date_reste_dans_le_mois_et_n_est_pas_modifiable_sur_une_saisie_de_session(): void
    {
        $t = $this->saisie();
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['date_prestation' => '2026-11-02', 'motif' => 'hors mois'])->assertStatus(422);
        $this->postJson("/api/timesheets/{$t->id}/adapter", ['date_prestation' => '2026-10-12', 'motif' => 'déplacée'])->assertOk();
        $this->assertSame('2026-10-12', $t->fresh()->date_prestation->toDateString());
    }

    public function test_un_professeur_ne_peut_ni_adapter_ni_lire_l_historique(): void
    {
        $t = $this->saisie();
        $this->actingAsRole('professeur');

        $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 2, 'motif' => 'test'])->assertForbidden();
        $this->getJson("/api/timesheets/{$t->id}/historique")->assertForbidden();
    }

    public function test_la_ressource_expose_can_adapt(): void
    {
        $t = $this->saisie();
        $this->actingAsRole('directeur');

        $this->getJson("/api/timesheets/{$t->id}")->assertJsonPath('can.adapt', true);
    }
}
