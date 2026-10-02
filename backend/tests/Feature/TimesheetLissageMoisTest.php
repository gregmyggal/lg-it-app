<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-01 T3 : détail professeur × mois et lissage (plafond 44,02 €/jour, tarif 20 €/h). */
class TimesheetLissageMoisTest extends TestCase
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

    private function ligne(string $date, float $h, string $statut = 'soumis', array $attrs = []): Timesheet
    {
        return Timesheet::create($attrs + [
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h,
            'type_activite' => 'animation', 'statut_validation' => $statut,
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/professeurs/{$this->prof->id}/timesheets-mois{$suffix}";
    }

    public function test_detail_du_mois_expose_lignes_jours_et_historique(): void
    {
        $this->ligne('2026-10-08', 3); // 60 € > 44,02 €
        $this->ligne('2026-10-09', 1);
        $this->actingAsRole('directeur');

        $j = $this->getJson($this->url('?annee=2026&mois=10'))->assertOk()->json();
        $this->assertCount(2, $j['lignes']);
        $this->assertEquals(44.02, $j['periode']['plafond_journalier_eur']);
        $jour8 = collect($j['jours'])->firstWhere('date', '2026-10-08');
        $this->assertTrue($jour8['depasse']);
        $this->assertFalse(collect($j['jours'])->firstWhere('date', '2026-10-09')['depasse']);
        $this->assertSame('a_valider', $j['resume']['statut_mois']);
    }

    public function test_apercu_auto_propose_de_ramener_les_jours_sous_le_plafond_sans_changer_le_total(): void
    {
        $this->ligne('2026-10-08', 3); // 60 €
        $this->actingAsRole('directeur');

        $r = $this->postJson($this->url('/lissage/apercu'), ['annee' => 2026, 'mois' => 10, 'mode' => 'auto'])->assertOk()->json();

        $this->assertSame([], $r['erreurs']);
        $this->assertSame([], $r['non_resolus']);
        $this->assertNotEmpty($r['deplacements']);
        $this->assertEquals($r['total_avant'], $r['total_apres']);
        foreach ($r['jours'] as $jour) {
            $this->assertLessThanOrEqual(44.02, $jour['apres']);
        }
    }

    public function test_appliquer_deplace_les_heures_trace_et_conserve_le_total(): void
    {
        $t = $this->ligne('2026-10-08', 3);
        $user = $this->actingAsRole('admin');

        $apercu = $this->postJson($this->url('/lissage/apercu'), ['annee' => 2026, 'mois' => 10, 'mode' => 'auto'])->json();
        $this->postJson($this->url('/lissage'), [
            'annee' => 2026, 'mois' => 10, 'deplacements' => $apercu['deplacements'], 'motif' => 'Respect du plafond',
        ])->assertOk();

        $heures = Timesheet::where('professeur_id', $this->prof->id)->sum('nombre_heures');
        $this->assertEquals(3.0, round((float) $heures, 2));
        $this->assertTrue(Timesheet::where('professeur_id', $this->prof->id)->where('id', '!=', $t->id)->exists());
        $this->assertEquals(1, $t->fresh()->lissage_applique);
        $audit = TimesheetAudit::where('action', 'lissage')->firstOrFail();
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('Respect du plafond', $audit->motif);

        $detail = $this->getJson($this->url('?annee=2026&mois=10'))->assertOk();
        $this->assertNotEmpty($detail->json('historique'));
        $jours = collect($detail->json('jours'));
        $this->assertSame(0, $jours->where('depasse', true)->count());
    }

    public function test_manuel_refuse_un_jour_cible_au_dessus_du_plafond_et_n_ecrit_rien(): void
    {
        $this->ligne('2026-10-08', 3);
        $this->ligne('2026-10-09', 2); // 40 € déjà
        $this->actingAsRole('directeur');

        $this->postJson($this->url('/lissage'), [
            'annee' => 2026, 'mois' => 10, 'motif' => 'test',
            'deplacements' => [['timesheet_id' => Timesheet::first()->id, 'date_to' => '2026-10-09', 'montant' => 20]],
        ])->assertStatus(422);

        $this->assertSame(0, TimesheetAudit::count());
        $this->assertSame(2, Timesheet::count());
    }

    public function test_date_hors_mois_motif_obligatoire_et_saisie_entiere_refuses(): void
    {
        $t = $this->ligne('2026-10-08', 3);
        $this->actingAsRole('directeur');

        $base = ['annee' => 2026, 'mois' => 10, 'motif' => 'x ok'];
        $this->postJson($this->url('/lissage'), $base + ['deplacements' => [['timesheet_id' => $t->id, 'date_to' => '2026-11-02', 'montant' => 10]]])->assertStatus(422);
        $this->postJson($this->url('/lissage'), ['deplacements' => [['timesheet_id' => $t->id, 'date_to' => '2026-10-09', 'montant' => 10]], 'annee' => 2026, 'mois' => 10])->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->postJson($this->url('/lissage'), $base + ['deplacements' => [['timesheet_id' => $t->id, 'date_to' => '2026-10-09', 'montant' => 60]]])->assertStatus(422);
        $this->assertSame(0, TimesheetAudit::count());
    }

    public function test_une_saisie_generee_n_est_pas_lissable_et_le_professeur_est_refuse(): void
    {
        $t = $this->ligne('2026-10-08', 3, 'genere');
        $this->actingAsRole('directeur');
        $this->postJson($this->url('/lissage'), ['annee' => 2026, 'mois' => 10, 'motif' => 'abc', 'deplacements' => [['timesheet_id' => $t->id, 'date_to' => '2026-10-09', 'montant' => 10]]])->assertStatus(422);

        $this->actingAsRole('professeur');
        $this->getJson($this->url('?annee=2026&mois=10'))->assertForbidden();
        $this->postJson($this->url('/lissage/apercu'), ['annee' => 2026, 'mois' => 10, 'mode' => 'auto'])->assertForbidden();
    }

    public function test_le_plafond_parametre_pilote_le_lissage(): void
    {
        $this->ligne('2026-10-08', 3); // 60 €
        $this->actingAsRole('admin');
        $this->putJson('/api/timesheet-parametres/2026', ['plafond_journalier_eur' => 60, 'plafond_annuel_eur' => 2000])->assertOk();

        $r = $this->postJson($this->url('/lissage/apercu'), ['annee' => 2026, 'mois' => 10, 'mode' => 'auto'])->assertOk()->json();
        $this->assertSame([], $r['deplacements']);
    }
}
