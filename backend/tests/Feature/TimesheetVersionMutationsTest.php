<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetVersion;
use App\Services\TimesheetConfirmationService;
use App\Services\TimesheetVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-04 T1b : validée v+1 sur toute mutation d'un mois validé, lecture des versions et écart. */
class TimesheetVersionMutationsTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    private Timesheet $a;

    private Timesheet $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prof = Professeur::factory()->create();
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        $this->a = $this->ligne('2026-10-05', 3.0, 'preparation');
        $this->b = $this->ligne('2026-10-07', 2.0);
    }

    private function ligne(string $date, float $h, string $type = 'animation'): Timesheet
    {
        return Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => $type, 'statut_validation' => 'brouillon']);
    }

    /** Le professeur soumet, la direction valide le mois entier ; renvoie le directeur connecté. */
    private function soumettreEtValider(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        $this->actingAsRole('directeur');
        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();
    }

    private function versions(string $type)
    {
        return TimesheetVersion::where(['professeur_id' => $this->prof->id, 'type' => $type])->orderBy('numero')->get();
    }

    private function assertValideeAJour(): void
    {
        $active = $this->versions('validee')->firstWhere('statut', 'active');
        $this->assertNotNull($active);
        $this->assertSame(app(TimesheetVersionService::class)->canoniser($this->prof, 2026, 10)['hash'], $active->content_hash);
    }

    public function test_adapter_un_mois_valide_cree_la_validee_v2_et_garde_la_v1(): void
    {
        $this->soumettreEtValider();
        $v1Hash = $this->versions('validee')[0]->content_hash;

        $this->postJson("/api/timesheets/{$this->a->id}/adapter", ['nombre_heures' => 1.5, 'motif' => 'Préparation plafonnée'])->assertOk();

        $v = $this->versions('validee');
        $this->assertSame([1, 2], $v->pluck('numero')->all());
        $this->assertSame(['remplacee', 'active'], $v->pluck('statut')->all());
        $this->assertSame($v1Hash, $v[0]->content_hash);
        $this->assertSame('1.50', $v[1]->lignesDecodees()[0]['heures']);
        $this->assertValideeAJour();
    }

    public function test_adapter_une_saisie_soumise_sans_validee_ne_cree_aucune_validee(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        $this->actingAsRole('directeur');

        $this->postJson("/api/timesheets/{$this->a->id}/adapter", ['nombre_heures' => 1.5, 'motif' => 'Test'])->assertOk();

        $this->assertCount(0, $this->versions('validee'));
    }

    public function test_un_lissage_direct_sur_un_mois_valide_cree_la_validee_suivante(): void
    {
        $this->soumettreEtValider();
        $this->actingAsRole('admin'); // le directeur ne lisse que des saisies soumises (policy)

        $this->postJson("/api/timesheets/{$this->a->id}/apply-lissage", ['date_to' => '2026-10-06', 'montant_to_move' => 10])->assertOk();

        $this->assertSame([1, 2], $this->versions('validee')->pluck('numero')->all());
        $this->assertCount(3, $this->versions('validee')[1]->lignesDecodees());
        $this->assertValideeAJour();
    }

    public function test_la_remise_en_brouillon_remplace_la_validee_et_garde_la_soumise(): void
    {
        $this->soumettreEtValider();
        $soumise = $this->versions('soumise')[0];
        $directeur = $this->actingAsRole('directeur');

        // La remise en brouillon refuse un mois « généré » ; ici le mois est confirmé.
        app(TimesheetConfirmationService::class)->remettreEnBrouillon($this->prof, 2026, 10, 'Erreur de date', $directeur);

        $this->assertSame(['remplacee'], $this->versions('validee')->pluck('statut')->all());
        $this->assertSame('active', $soumise->fresh()->statut);
        $this->assertSame(0, TimesheetVersion::whereNotNull('actif_cle')->where('type', 'validee')->count());
    }

    public function test_traiter_une_contestation_remplace_la_validee_active(): void
    {
        $this->soumettreEtValider();
        $directeur = $this->actingAsRole('directeur');
        $service = app(TimesheetConfirmationService::class);
        $service->contester($this->prof, 2026, 10, 'Il manque une heure', $directeur);

        $service->traiterContestation($this->prof, 2026, 10, 'Vérifié', $directeur);

        $this->assertSame(['remplacee'], $this->versions('validee')->pluck('statut')->all());
        // Revalidation du mois : validée v2.
        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();
        $this->assertSame([1, 2], $this->versions('validee')->pluck('numero')->all());
        $this->assertValideeAJour();
    }

    public function test_api_directeur_ecart_modifiee_ajoutee_avec_motif(): void
    {
        $this->soumettreEtValider();
        $this->postJson("/api/timesheets/{$this->a->id}/adapter", ['nombre_heures' => 2.0, 'motif' => 'Préparation plafonnée'])->assertOk();
        $this->actingAsRole('admin');
        $this->postJson("/api/timesheets/{$this->b->id}/apply-lissage", ['date_to' => '2026-10-08', 'montant_to_move' => 10, 'motif' => 'Plafond journalier'])->assertOk();

        $r = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertOk();

        $this->assertTrue($r->json('disponibilite.soumise'));
        $this->assertSame(1, $r->json('soumise.numero'));
        $this->assertSame(3, $r->json('validee.numero'));
        $this->assertNotEmpty($r->json('validee.empreinte'));
        $this->assertSame('Préparation plafonnée', collect($r->json('ecart.lignes'))->firstWhere('id', $this->a->id)['motif']);
        $etats = collect($r->json('ecart.lignes'))->pluck('etat', 'id');
        $this->assertSame('modifiee', $etats[$this->a->id]);
        $this->assertSame('modifiee', $etats[$this->b->id]);
        $ajoutee = collect($r->json('ecart.lignes'))->firstWhere('etat', 'ajoutee');
        $this->assertSame($this->b->id, $ajoutee['apres']['origine_id']);
        $this->assertSame('Plafond journalier', $ajoutee['motif']); // hérité de la ligne d'origine
        $this->assertFalse($r->json('ecart.identique'));
        $this->assertEquals(-20.0, $r->json('ecart.ecart_eur')); // 3 h → 2 h de préparation (−20 €), lissage neutre
        $this->assertCount(3, $r->json('historique.validees'));
    }

    public function test_un_mois_valide_tel_que_soumis_est_identique(): void
    {
        $this->soumettreEtValider();

        $r = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertOk();

        $this->assertTrue($r->json('ecart.identique'));
        $this->assertSame(0, $r->json('ecart.ecart_eur'));
    }

    public function test_un_mois_sans_version_est_non_disponible_sans_erreur(): void
    {
        $this->a->update(['statut_validation' => 'soumis']);
        $this->actingAsRole('admin');

        $r = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertOk();

        $this->assertFalse($r->json('disponibilite.soumise'));
        $this->assertFalse($r->json('disponibilite.validee'));
        $this->assertNull($r->json('ecart'));
    }

    public function test_une_version_purgee_n_expose_que_ses_totaux(): void
    {
        $this->soumettreEtValider();
        \Illuminate\Support\Facades\DB::table('timesheet_versions')->where('type', 'soumise')->update(['lignes' => null, 'purge_at' => now()]);

        $r = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertOk();

        $this->assertTrue($r->json('soumise.detail_efface'));
        $this->assertSame([], $r->json('soumise.lignes'));
        $this->assertNull($r->json('ecart'));
        $this->assertNotNull($r->json('soumise.total_eur'));
    }

    public function test_le_professeur_voit_ses_versions_sans_empreinte_ni_auteur(): void
    {
        $this->soumettreEtValider();
        Sanctum::actingAs($this->prof->user);

        $r = $this->getJson('/api/timesheets/mes-versions?annee=2026&mois=10')->assertOk();

        $this->assertSame(1, $r->json('validee.numero'));
        $this->assertArrayNotHasKey('empreinte', $r->json('validee'));
        $this->assertArrayNotHasKey('auteur', $r->json('validee'));
    }

    public function test_un_professeur_ne_peut_pas_lire_les_versions_d_un_autre(): void
    {
        $this->soumettreEtValider();
        $autre = Professeur::factory()->create();
        Sanctum::actingAs($autre->user);

        $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertForbidden();
        // Sa propre vue ne révèle rien du mois d'un autre.
        $this->getJson('/api/timesheets/mes-versions?annee=2026&mois=10')->assertOk()->assertJsonPath('disponibilite.soumise', false);
    }

    public function test_versions_inaccessibles_sans_authentification(): void
    {
        $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois/versions?annee=2026&mois=10")->assertUnauthorized();
    }
}
