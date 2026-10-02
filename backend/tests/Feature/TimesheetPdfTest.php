<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-01 T5 : fiche de défraiement PDF (génération, lot, déverrouillage, téléchargement) et types de lignes. */
class TimesheetPdfTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 11.75, 'date_debut' => '2026-01-01']);
    }

    private function ligne(Professeur $p, string $type, float $n, string $date = '2026-10-10', string $statut = 'confirme', bool $signee = true): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $p->id, 'date_prestation' => $date, 'nombre_heures' => $n, 'type_activite' => $type,
            'statut_validation' => $statut, 'signature_professeur' => $signee ? now() : null,
        ]);
    }

    private function prete(?Professeur $p = null): Professeur
    {
        $p ??= $this->prof;
        $this->ligne($p, 'animation', 3, '2026-10-01');
        $this->ligne($p, 'deplacement', 1, '2026-10-30');

        return $p;
    }

    private function url(Professeur $p, string $s): string
    {
        return "/api/professeurs/{$p->id}/{$s}";
    }

    public function test_les_types_de_ligne_alignes_sur_la_fiche_et_le_montant_d_un_deplacement(): void
    {
        $user = $this->actingAsRole('professeur');
        $prof = Professeur::factory()->create(['user_id' => $user->id]);
        ProfesseurTarif::create(['professeur_id' => $prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);

        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-02', 'nombre_heures' => 2, 'type_activite' => 'cours'])
            ->assertCreated()->assertJsonPath('montant_brut', 40);
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-03', 'nombre_heures' => 2, 'type_activite' => 'deplacement'])
            ->assertCreated()->assertJsonPath('montant_brut', 15); // 2 × 7,50 €
        $this->postJson('/api/timesheets', ['date_prestation' => '2026-10-03', 'nombre_heures' => 2, 'type_activite' => 'autre'])->assertStatus(422);
    }

    public function test_generation_produit_un_pdf_version_1_et_passe_les_saisies_a_genere(): void
    {
        $this->prete();
        $directeur = $this->actingAsRole('directeur');

        $r = $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertCreated();

        $r->assertJsonPath('data.version', 1);
        $this->assertEquals(42.75, $r->json('data.total_eur')); // 3 h × 11,75 + 7,50
        $pdf = TimesheetPdf::firstOrFail();
        $this->assertSame($directeur->id, $pdf->generated_by);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->chemin));
        $this->assertSame(0, Timesheet::where('statut_validation', '!=', 'genere')->count());
        $this->assertStringContainsString('Fiche de défraiement_', $r->json('data.nom_fichier'));
    }

    public function test_generation_bloquee_sans_signature_sans_iban_ou_deja_generee(): void
    {
        $this->actingAsRole('admin');
        $this->ligne($this->prof, 'animation', 3, '2026-10-01', 'confirme', false);
        $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])
            ->assertStatus(422)->assertJsonPath('errors.pdf.0', 'En attente de la signature du professeur');

        $sansIban = Professeur::factory()->create(['compte_bancaire' => null]);
        ProfesseurTarif::create(['professeur_id' => $sansIban->id, 'tarif_horaire_eur' => 10, 'date_debut' => '2026-01-01']);
        $this->ligne($sansIban, 'animation', 1);
        $this->postJson($this->url($sansIban, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])
            ->assertStatus(422)->assertJsonPath('errors.pdf.0', 'Compte bancaire manquant');

        $deja = $this->prete(Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']));
        ProfesseurTarif::create(['professeur_id' => $deja->id, 'tarif_horaire_eur' => 10, 'date_debut' => '2026-01-01']);
        $this->postJson($this->url($deja, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertCreated();
        $this->postJson($this->url($deja, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertStatus(422);
    }

    public function test_un_professeur_ne_genere_rien_mais_telecharge_sa_fiche(): void
    {
        $this->prete();
        $this->actingAsRole('directeur');
        $id = $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->json('data.id');

        Sanctum::actingAs($this->prof->user);
        $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertForbidden();
        $this->get("/api/timesheet-pdfs/{$id}/telecharger")->assertOk()->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs(Professeur::factory()->create()->user);
        $this->get("/api/timesheet-pdfs/{$id}/telecharger")->assertForbidden();
    }

    public function test_lot_zip_atomique_avec_liste_des_bloquants(): void
    {
        $b = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $b->id, 'tarif_horaire_eur' => 10, 'date_debut' => '2026-01-01']);
        $this->prete();
        $this->ligne($b, 'animation', 1, '2026-10-05', 'soumis', false);
        $this->actingAsRole('directeur');

        $this->postJson('/api/timesheets-mois/pdf-lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [$this->prof->id, $b->id]])
            ->assertStatus(422)->assertJsonPath('bloquants.0.professeur_id', $b->id);
        $this->assertSame(0, TimesheetPdf::count());

        $this->post('/api/timesheets-mois/pdf-lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [$this->prof->id]])
            ->assertOk()->assertHeader('content-type', 'application/zip');
        $this->assertSame(1, TimesheetPdf::count());
    }

    public function test_deverrouillage_reserve_a_l_admin_avec_motif_puis_nouvelle_version(): void
    {
        $this->prete();
        $this->actingAsRole('directeur');
        $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertCreated();

        $u = $this->url($this->prof, 'timesheets-mois/deverrouiller');
        $this->postJson($u, ['annee' => 2026, 'mois' => 10, 'motif' => 'correction'])->assertForbidden();

        $this->actingAsRole('admin');
        $this->postJson($u, ['annee' => 2026, 'mois' => 10])->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->postJson($u, ['annee' => 2026, 'mois' => 10, 'motif' => 'Erreur de saisie'])->assertOk()->assertJsonPath('deverrouilles', 2);
        $this->assertSame('confirme', Timesheet::first()->statut_validation);

        $this->postJson($this->url($this->prof, 'timesheet-pdfs'), ['annee' => 2026, 'mois' => 10])->assertCreated()->assertJsonPath('data.version', 2);
        $this->assertSame(2, TimesheetPdf::count());
    }

    public function test_apercu_ne_change_aucun_statut_et_plus_de_15_lignes_donnent_une_seconde_page(): void
    {
        for ($j = 1; $j <= 17; $j++) {
            $this->ligne($this->prof, 'animation', 1, sprintf('2026-10-%02d', $j), 'soumis', false);
        }
        $this->actingAsRole('directeur');

        $r = $this->get($this->url($this->prof, 'timesheet-pdfs/apercu').'?annee=2026&mois=10')->assertOk();
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page[^s]/', $r->getContent()));
        $this->assertSame(17, Timesheet::where('statut_validation', 'soumis')->count());
    }
}
