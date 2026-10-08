<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-02 : remise en brouillon d'un mois par la direction (mois entier, impossible après génération du PDF). */
class TimesheetRemiseBrouillonTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prof = Professeur::factory()->create();
    }

    private function ligne(string $statut, string $date = '2026-10-10', bool $signee = false): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => 1, 'type_activite' => 'animation',
            'statut_validation' => $statut, 'signature_professeur' => $signee ? now() : null,
        ]);
    }

    private function url(): string
    {
        return "/api/professeurs/{$this->prof->id}/timesheets-mois/remettre-en-brouillon";
    }

    private function payload(array $extra = []): array
    {
        return ['annee' => 2026, 'mois' => 10] + $extra;
    }

    /** @dataProvider rolesStaff */
    public function test_le_staff_remet_le_mois_entier_en_brouillon_signatures_retirees(string $role): void
    {
        $a = $this->ligne('confirme', '2026-10-10', true);
        $b = $this->ligne('soumis', '2026-10-11');
        $autreMois = $this->ligne('confirme', '2026-11-02', true);
        $this->actingAsRole($role);

        $this->postJson($this->url(), $this->payload(['motif' => 'Heures du 12 oubliées']))
            ->assertOk()->assertJsonPath('remises_en_brouillon', 2);

        foreach ([$a, $b] as $t) {
            $this->assertSame('brouillon', $t->fresh()->statut_validation);
            $this->assertNull($t->fresh()->signature_professeur);
        }
        $this->assertSame('confirme', $autreMois->fresh()->statut_validation);
        $audit = TimesheetAudit::where(['timesheet_id' => $a->id, 'action' => 'remise_brouillon'])->firstOrFail();
        $this->assertSame('Heures du 12 oubliées', $audit->motif);
        $this->assertTrue($audit->avant['signee']);
        $notif = $this->prof->user->notifications()->firstOrFail();
        $this->assertSame('remise_brouillon', $notif->data['code']);
    }

    public static function rolesStaff(): array
    {
        return [['directeur'], ['admin']];
    }

    public function test_impossible_des_qu_un_pdf_est_genere_et_rien_n_est_modifie(): void
    {
        $a = $this->ligne('confirme');
        $this->ligne('genere', '2026-10-12');
        $this->actingAsRole('directeur');

        $this->postJson($this->url(), $this->payload(['motif' => 'Correction']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'mois suivant'));

        $this->assertSame('confirme', $a->fresh()->statut_validation);
        $this->assertSame(0, TimesheetAudit::count());
    }

    public function test_motif_obligatoire_et_mois_sans_saisie_rouvrable(): void
    {
        $this->ligne('brouillon');
        $this->actingAsRole('directeur');

        $this->postJson($this->url(), $this->payload())->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->postJson($this->url(), $this->payload(['motif' => 'Correction']))->assertStatus(422);
    }

    public function test_le_professeur_ne_peut_pas_remettre_en_brouillon(): void
    {
        $this->ligne('confirme');
        Sanctum::actingAs($this->prof->user);

        $this->postJson($this->url(), $this->payload(['motif' => 'Correction']))->assertForbidden();
    }

    public function test_le_detail_expose_la_possibilite_de_remise_en_brouillon(): void
    {
        $this->ligne('confirme', '2026-10-10', true);
        $this->actingAsRole('directeur');
        $q = "/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10";

        $this->getJson($q)->assertOk()->assertJsonPath('remise_brouillon.possible', true)
            ->assertJsonPath('remise_brouillon.lignes', 1)->assertJsonPath('remise_brouillon.signatures', 1);

        $this->ligne('genere', '2026-10-12');
        $this->getJson($q)->assertOk()->assertJsonPath('remise_brouillon.possible', false);
    }

    public function test_le_professeur_peut_corriger_puis_resoumettre(): void
    {
        $t = $this->ligne('confirme', '2026-10-10', true);
        $this->actingAsRole('directeur');
        $this->postJson($this->url(), $this->payload(['motif' => 'Correction']))->assertOk();

        Sanctum::actingAs($this->prof->user);
        $this->putJson("/api/timesheets/{$t->id}", ['nombre_heures' => 2])->assertOk();
        $this->postJson("/api/timesheets/{$t->id}/submit")->assertOk();
        $this->assertSame('soumis', $t->fresh()->statut_validation);
    }

    public function test_le_professeur_voit_le_motif_de_la_remise_en_brouillon_jusqu_a_la_resoumission(): void
    {
        $t = $this->ligne('confirme', '2026-10-10', true);
        $this->actingAsRole('directeur');
        $this->postJson($this->url(), $this->payload(['motif' => 'Heure oubliée']))->assertOk();

        Sanctum::actingAs($this->prof->user);
        $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->assertJsonPath('remise_brouillon.motif', 'Heure oubliée');

        $this->postJson("/api/timesheets/{$t->id}/submit")->assertOk();
        $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->assertJsonPath('remise_brouillon', null);
    }
}
