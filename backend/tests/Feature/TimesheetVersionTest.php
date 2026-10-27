<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\TimesheetVersion;
use App\Services\TimesheetConfirmationService;
use App\Services\TimesheetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-04 T1a (ADR-0002) : instantanés « soumise » et « validée », validation du mois entier, chemins unifiés. */
class TimesheetVersionTest extends TestCase
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

    private function ligne(string $date, float $h, string $statut = 'brouillon', string $type = 'animation'): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h,
            'type_activite' => $type, 'statut_validation' => $statut,
        ]);
    }

    private function soumettre(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
    }

    private function versions(string $type)
    {
        return TimesheetVersion::where(['professeur_id' => $this->prof->id, 'type' => $type])->orderBy('numero')->get();
    }

    public function test_la_soumission_fige_une_version_soumise_avec_totaux_et_empreinte(): void
    {
        $a = $this->ligne('2026-10-05', 2.0, type: 'preparation');
        $b = $this->ligne('2026-10-07', 3.0);

        $this->soumettre();

        $v = $this->versions('soumise');
        $this->assertCount(1, $v);
        $this->assertSame(1, $v[0]->numero);
        $this->assertSame('active', $v[0]->statut);
        $this->assertEquals(5.0, $v[0]->total_heures);
        $this->assertEquals(100.0, $v[0]->total_eur); // (2 + 3) h × 20 €
        $this->assertSame(hash('sha256', $v[0]->lignes), $v[0]->content_hash);
        $this->assertSame([$a->id, $b->id], array_column($v[0]->lignesDecodees(), 'id'));
        $this->assertSame('2.00', $v[0]->lignesDecodees()[0]['heures']);
        $this->assertSame('40.00', $v[0]->lignesDecodees()[0]['montant']);
    }

    public function test_l_empreinte_se_retrouve_apres_relecture_en_base(): void
    {
        $this->ligne('2026-10-05', 2.5);
        $this->soumettre();

        $relue = TimesheetVersion::firstOrFail();

        $this->assertSame($relue->content_hash, hash('sha256', $relue->lignes));
        $this->assertSame('[{"id":1,"date":"2026-10-05","type":"animation","heures":"2.50","unite":"20.00","montant":"50.00","session_id":null,"cours_id":null,"origine_id":null}]', str_replace('"id":'.Timesheet::first()->id.',', '"id":1,', $relue->lignes));
    }

    public function test_une_seule_soumission_par_mois_et_une_seule_version(): void
    {
        $this->ligne('2026-10-05', 2.0);
        $this->soumettre();

        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertStatus(422);

        $this->assertCount(1, $this->versions('soumise'));
    }

    public function test_remise_en_brouillon_puis_resoumission_cree_la_soumise_v2_et_garde_la_v1(): void
    {
        $a = $this->ligne('2026-10-05', 2.0);
        $this->soumettre();
        $v1Hash = $this->versions('soumise')[0]->content_hash;
        $directeur = $this->actingAsRole('directeur');
        app(TimesheetConfirmationService::class)->remettreEnBrouillon($this->prof, 2026, 10, 'Date erronée', $directeur);
        $a->refresh()->update(['nombre_heures' => 3.0]);

        $this->soumettre();

        $v = $this->versions('soumise');
        $this->assertSame([1, 2], $v->pluck('numero')->all());
        $this->assertSame(['remplacee', 'active'], $v->pluck('statut')->all());
        $this->assertSame($v1Hash, $v[0]->content_hash); // la v1 n'a pas bougé
        $this->assertNotSame($v1Hash, $v[1]->content_hash);
    }

    public function test_valider_le_mois_fige_la_validee_et_laisse_la_soumise_intacte(): void
    {
        $a = $this->ligne('2026-10-05', 2.0);
        $b = $this->ligne('2026-10-07', 3.0);
        $this->soumettre();
        $soumise = $this->versions('soumise')[0];
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id, $b->id]])->assertOk()->assertJsonPath('mois_valides', 1);

        $validee = $this->versions('validee');
        $this->assertCount(1, $validee);
        $this->assertSame('active', $validee[0]->statut);
        $this->assertSame($soumise->content_hash, $validee[0]->content_hash); // aucun ajustement : même contenu
        $this->assertSame('active', $soumise->fresh()->statut);
        $this->assertSame($soumise->lignes, $soumise->fresh()->lignes);
    }

    public function test_une_validation_partielle_du_mois_est_refusee_et_ne_modifie_rien(): void
    {
        $a = $this->ligne('2026-10-05', 2.0);
        $b = $this->ligne('2026-10-07', 3.0);
        $this->soumettre();
        $this->actingAsRole('directeur');

        $r = $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id]])->assertStatus(422);

        $this->assertSame($b->id, $r->json('invalides.0.id'));
        $this->assertSame('soumis', $a->fresh()->statut_validation);
        $this->assertSame('soumis', $b->fresh()->statut_validation);
        $this->assertCount(0, $this->versions('validee'));
    }

    public function test_un_mois_avec_contestation_en_cours_ne_se_valide_pas(): void
    {
        $a = $this->ligne('2026-10-05', 2.0, 'soumis');
        $this->ligne('2026-10-07', 3.0, 'conteste');
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id]])->assertStatus(422);

        $this->assertCount(0, $this->versions('validee'));
    }

    public function test_le_lot_est_tout_ou_rien_si_un_mois_est_incomplet(): void
    {
        $complet = $this->ligne('2026-10-05', 2.0, 'soumis');
        $autre = Professeur::factory()->create();
        $a = Timesheet::create(['professeur_id' => $autre->id, 'date_prestation' => '2026-10-05', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);
        Timesheet::create(['professeur_id' => $autre->id, 'date_prestation' => '2026-10-06', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);
        $this->actingAsRole('admin');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$complet->id, $a->id]])->assertStatus(422);

        $this->assertSame('soumis', $complet->fresh()->statut_validation);
        $this->assertSame(0, TimesheetVersion::count());
    }

    public function test_plusieurs_mois_complets_sont_valides_dans_un_meme_lot(): void
    {
        $a = $this->ligne('2026-10-05', 2.0, 'soumis');
        $autre = Professeur::factory()->create();
        $b = Timesheet::create(['professeur_id' => $autre->id, 'date_prestation' => '2026-10-09', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'soumis']);
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$a->id, $b->id]])->assertOk()->assertJsonPath('mois_valides', 2);

        $this->assertSame(2, TimesheetVersion::where('type', 'validee')->count());
    }

    public function test_la_validee_reflete_le_lissage_et_relie_la_ligne_a_son_origine(): void
    {
        $a = $this->ligne('2026-10-05', 3.0, 'soumis'); // 60 € > plafond 44,02 €
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets/valider-lot', [
            'ids' => [$a->id],
            'lissages' => [['timesheet_id' => $a->id, 'date_to' => '2026-10-06', 'montant_to_move' => 15.98]],
        ])->assertOk();

        $lignes = $this->versions('validee')[0]->lignesDecodees();
        $this->assertCount(2, $lignes);
        $this->assertSame('2.20', $lignes[0]['heures']);
        $this->assertSame('0.80', $lignes[1]['heures']);
        $this->assertSame($a->id, $lignes[1]['origine_id']);
        $this->assertSame(['confirme', 'confirme'], Timesheet::orderBy('id')->pluck('statut_validation')->all());
        $this->assertSame(1, TimesheetAudit::where('action', TimesheetAudit::ACTION_LISSAGE)->count());
        $this->assertEquals(60.0, $this->versions('validee')[0]->total_eur);
    }

    public function test_la_route_validate_d_une_saisie_n_existe_plus(): void
    {
        $a = $this->ligne('2026-10-05', 2.0, 'soumis');
        $this->actingAsRole('admin');

        $this->postJson("/api/timesheets/{$a->id}/validate")->assertStatus(404);
        $this->assertSame('soumis', $a->fresh()->statut_validation);
    }

    public function test_apply_lissage_passe_par_le_service_du_mois_avec_audit(): void
    {
        $a = $this->ligne('2026-10-05', 3.0, 'soumis');
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$a->id}/apply-lissage", ['date_to' => '2026-10-06', 'montant_to_move' => 15.98])->assertOk();

        $this->assertSame(1, TimesheetAudit::where('action', TimesheetAudit::ACTION_LISSAGE)->count());
        $this->assertSame('soumis', Timesheet::where('commentaire', 'like', 'Lissé depuis%')->firstOrFail()->statut_validation); // plus créée « confirme »
    }

    public function test_apply_lissage_refuse_un_brouillon(): void
    {
        $a = $this->ligne('2026-10-05', 3.0, 'brouillon');
        $this->actingAsRole('admin');

        $this->postJson("/api/timesheets/{$a->id}/apply-lissage", ['date_to' => '2026-10-06', 'montant_to_move' => 15.98])->assertStatus(422)->assertJsonStructure(['error']);

        $this->assertSame(1, Timesheet::count());
    }

    public function test_une_version_est_immuable(): void
    {
        $this->ligne('2026-10-05', 2.0);
        $this->soumettre();
        $version = TimesheetVersion::firstOrFail();

        try {
            $version->update(['lignes' => '[]']);
            $this->fail('La modification des lignes aurait dû être refusée.');
        } catch (LogicException) {
            $this->assertSame(hash('sha256', $version->fresh()->lignes), $version->fresh()->content_hash);
        }
        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_une_seule_version_active_par_type_et_numerotation_continue(): void
    {
        $this->ligne('2026-10-05', 2.0, 'confirme');
        $service = app(TimesheetVersionService::class);

        $service->figerValidee($this->prof, 2026, 10, null);
        $service->figerValidee($this->prof, 2026, 10, null);

        $v = $this->versions('validee');
        $this->assertSame([1, 2], $v->pluck('numero')->all());
        $this->assertSame(['remplacee', 'active'], $v->pluck('statut')->all());
        $this->assertSame(1, TimesheetVersion::whereNotNull('actif_cle')->count());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('timesheet_versions')->insert(['professeur_id' => $this->prof->id, 'annee' => 2026, 'mois' => 10, 'type' => 'validee', 'numero' => 9, 'statut' => 'active', 'actif_cle' => TimesheetVersion::cleActive($this->prof->id, 2026, 10, 'validee'), 'total_heures' => 0, 'total_eur' => 0, 'content_hash' => str_repeat('a', 64)]);
    }
}
