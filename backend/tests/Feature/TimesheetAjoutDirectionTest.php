<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Notifications\NotificationTimesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-07 : la direction/admin ajoute des heures (un commentaire obligatoire par ligne) au mois en brouillon d'un professeur. */
class TimesheetAjoutDirectionTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->prof = Professeur::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ajouter(array $lignes, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/professeurs/{$this->prof->id}/timesheets-mois/ajouter-heures", $extra + ['annee' => 2026, 'mois' => 10, 'lignes' => $lignes]);
    }

    private function ligne(string $date = '2026-10-12', float $h = 1.0, string $commentaire = 'Réunion pédagogique non encodée'): array
    {
        return ['date_prestation' => $date, 'type_activite' => 'preparation', 'nombre_heures' => $h, 'commentaire' => $commentaire];
    }

    public function test_la_direction_ajoute_plusieurs_lignes_chacune_avec_son_commentaire(): void
    {
        $dir = $this->actingAsRole('directeur');

        $r = $this->ajouter([$this->ligne(), $this->ligne('2026-10-14', 2.5, 'Remplacement oublié')])->assertCreated();

        $this->assertCount(2, $r->json('lignes'));
        $this->assertSame('brouillon', $r->json('lignes.0.statut_validation'));
        $this->assertSame($dir->id, $r->json('lignes.0.ajoute_par.id'));
        $this->assertSame('Remplacement oublié', $r->json('lignes.1.commentaire'));
        $this->assertSame(2, Timesheet::where('professeur_id', $this->prof->id)->where('ajoute_par_user_id', $dir->id)->count());
        $this->assertSame(2, TimesheetAudit::where('action', 'ajout_direction')->count());
        $this->assertSame('Réunion pédagogique non encodée', TimesheetAudit::where('action', 'ajout_direction')->orderBy('id')->first()->motif);
    }

    public function test_l_admin_peut_ajouter_et_le_professeur_est_notifie(): void
    {
        $this->actingAsRole('admin');
        $this->ajouter([$this->ligne()])->assertCreated();

        $codes = $this->prof->user->notifications()->get()->pluck('data.code')->all();
        $this->assertContains(NotificationTimesheet::HEURES_AJOUTEES, $codes);
    }

    public function test_le_commentaire_est_obligatoire_pour_chaque_ligne(): void
    {
        $this->actingAsRole('directeur');

        $this->ajouter([$this->ligne(), $this->ligne('2026-10-14', 1, '')])->assertStatus(422)->assertJsonValidationErrors('lignes.1.commentaire');
        $this->assertSame(0, Timesheet::count());
    }

    public function test_validation_heures_type_et_date_dans_le_mois(): void
    {
        $this->actingAsRole('directeur');

        foreach ([0.25, 25, 1.1] as $h) {
            $this->ajouter([$this->ligne('2026-10-12', $h)])->assertStatus(422)->assertJsonValidationErrors('lignes.0.nombre_heures');
        }
        $this->ajouter([['type_activite' => 'deplacement'] + $this->ligne()])->assertStatus(422)->assertJsonValidationErrors('lignes.0.type_activite');
        $this->ajouter([$this->ligne('2026-11-02')])->assertStatus(422)->assertJsonValidationErrors('lignes.0.date_prestation');
        $this->ajouter([])->assertStatus(422);
        $this->assertSame(0, Timesheet::count());
    }

    public function test_un_mois_deja_soumis_refuse_l_ajout(): void
    {
        $this->actingAsRole('directeur');
        Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => '2026-10-05', 'nombre_heures' => 2, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);

        $this->ajouter([$this->ligne()])->assertStatus(422);
        $this->assertSame(1, Timesheet::count());
        $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk()
            ->assertJsonPath('ajout_heures.possible', false)->assertJsonPath('ajout_heures.raison_impossible', 'mois_soumis');
    }

    public function test_un_autre_mois_soumis_n_empeche_pas_l_ajout(): void
    {
        $this->actingAsRole('directeur');
        Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => '2026-09-05', 'nombre_heures' => 2, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);

        $this->ajouter([$this->ligne()])->assertCreated();
    }

    public function test_un_professeur_ne_peut_pas_ajouter_des_heures_a_un_collegue(): void
    {
        $this->actingAsRole('professeur');
        $this->ajouter([$this->ligne()])->assertForbidden();
        $this->assertSame(0, Timesheet::count());
    }

    public function test_le_professeur_voit_la_ligne_ajoutee_avec_son_commentaire(): void
    {
        $this->actingAsRole('directeur');
        $this->ajouter([$this->ligne()])->assertCreated();

        Sanctum::actingAs($this->prof->user);
        $libres = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk()->json('libres');
        $this->assertCount(1, $libres);
        $this->assertSame('Réunion pédagogique non encodée', $libres[0]['commentaire']);
        $this->assertNotNull($libres[0]['ajoute_par']['nom']);
    }

    public function test_le_detail_direction_expose_le_marqueur_et_l_historique(): void
    {
        $this->actingAsRole('directeur');
        $this->ajouter([$this->ligne()])->assertCreated();

        $d = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk();
        $this->assertTrue($d->json('ajout_heures.possible'));
        $this->assertNotNull($d->json('lignes.0.ajoute_par'));
        $this->assertSame('ajout_direction', $d->json('historique.0.action'));
    }
}
