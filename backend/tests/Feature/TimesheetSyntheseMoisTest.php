<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-01 T2 : synthèse mensuelle par professeur. */
class TimesheetSyntheseMoisTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private function prof(string $prenom, bool $tarif = true, ?string $iban = 'BE68539007547034'): Professeur
    {
        $p = Professeur::factory()->create(['prenom' => $prenom, 'compte_bancaire' => $iban]);
        if ($tarif) {
            ProfesseurTarif::create(['professeur_id' => $p->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        }

        return $p;
    }

    private function ligne(Professeur $p, string $statut, float $h = 1, string $date = '2026-10-10', string $type = 'animation', array $attrs = []): Timesheet
    {
        return Timesheet::create($attrs + [
            'professeur_id' => $p->id, 'date_prestation' => $date, 'nombre_heures' => $h,
            'type_activite' => $type, 'statut_validation' => $statut,
        ]);
    }

    private function synthese(): array
    {
        return $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertOk()->json();
    }

    private function ligneDe(array $json, Professeur $p): array
    {
        return collect($json['professeurs'])->firstWhere('professeur_id', $p->id);
    }

    public function test_statut_du_mois_est_le_plus_bas_des_lignes(): void
    {
        $a = $this->prof('Alice');
        $b = $this->prof('Bruno');
        $c = $this->prof('Chloé');
        $d = $this->prof('David');
        $e = $this->prof('Emma');
        $this->ligne($a, 'brouillon');
        $this->ligne($b, 'soumis');
        $this->ligne($b, 'confirme', 1, '2026-10-11');
        $this->ligne($c, 'confirme');
        $this->ligne($d, 'confirme', 1, '2026-10-10', 'animation', ['signature_professeur' => now()]);
        $this->ligne($e, 'genere');
        $this->actingAsRole('directeur');

        $j = $this->synthese();
        $this->assertSame('brouillon', $this->ligneDe($j, $a)['statut_mois']);
        $this->assertSame('a_valider', $this->ligneDe($j, $b)['statut_mois']);
        $this->assertSame('attente_prof', $this->ligneDe($j, $c)['statut_mois']);
        $this->assertSame('pret_pdf', $this->ligneDe($j, $d)['statut_mois']);
        $this->assertSame('genere', $this->ligneDe($j, $e)['statut_mois']);
        $this->assertSame(1, $j['kpis']['a_valider']);
        $this->assertSame(1, $j['kpis']['pret_pdf']);
        $this->assertSame(4, $j['kpis']['soumis']);
    }

    public function test_totaux_heures_et_montants(): void
    {
        $a = $this->prof('Alice');
        $this->ligne($a, 'soumis', 2, '2026-10-01', 'animation');
        $this->ligne($a, 'soumis', 1.5, '2026-10-02', 'preparation');
        $this->ligne($a, 'soumis', 4, '2026-11-02'); // autre mois : ignoré
        $this->actingAsRole('admin');

        $l = $this->ligneDe($this->synthese(), $a);
        $this->assertEquals(2, $l['heures_animation']);
        $this->assertEquals(1.5, $l['heures_preparation']);
        $this->assertEquals(70, $l['total_eur']);
        $this->assertSame(2, $l['lignes']);
    }

    public function test_alertes_depassement_tarif_et_compte_bancaire(): void
    {
        $a = $this->prof('Alice', true, null);
        $b = $this->prof('Bruno', false);
        $this->ligne($a, 'soumis', 3); // 60 € > 44,02 € le même jour
        $this->ligne($b, 'soumis', 1);
        $this->actingAsRole('directeur');

        $j = $this->synthese();
        $codesA = collect($this->ligneDe($j, $a)['alertes'])->pluck('code')->all();
        $this->assertContains('depassement', $codesA);
        $this->assertContains('compte_bancaire_manquant', $codesA);
        $this->assertContains('tarif_absent', collect($this->ligneDe($j, $b)['alertes'])->pluck('code')->all());
    }

    public function test_lignes_ajustees_comptees_depuis_l_historique(): void
    {
        $a = $this->prof('Alice');
        $t = $this->ligne($a, 'soumis', 1);
        $this->actingAsRole('directeur');
        $this->postJson("/api/timesheets/{$t->id}/adapter", ['nombre_heures' => 2, 'motif' => 'correction'])->assertOk();

        $l = $this->ligneDe($this->synthese(), $a);
        $this->assertSame(1, $l['lignes_ajustees']);
        $this->assertNotNull($l['derniere_action_at']);
    }

    public function test_reserve_au_staff_et_validation(): void
    {
        $this->actingAsRole('professeur');
        $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertForbidden();

        $this->actingAsRole('admin');
        $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=13')->assertStatus(422);
        $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=3')->assertOk()->assertJsonPath('professeurs', []);
    }
}
