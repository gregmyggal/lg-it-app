<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetPdf;
use App\Models\TimesheetSignature;
use App\Models\TimesheetVersion;
use App\Services\SignatureNumeriqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** TS-04 T1c : signature et PDF uniquement sur la version validée du mois, encore à jour. */
class TimesheetVersionGardeTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;
    use SigneMois;

    private Professeur $prof;

    private Timesheet $a;

    private Timesheet $b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        $this->creerSignature($this->prof->user);
        $this->a = $this->ligne('2026-10-05', 2.0);
        $this->b = $this->ligne('2026-10-07', 2.0);
    }

    private function ligne(string $date, float $h, string $statut = 'brouillon'): Timesheet
    {
        return Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => 'animation', 'statut_validation' => $statut]);
    }

    private function soumettreEtValider(): void
    {
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        $this->actingAsRole('directeur');
        $this->postJson('/api/timesheets/valider-lot', ['ids' => [$this->a->id, $this->b->id]])->assertOk();
    }

    private function signer(): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->prof->user);

        return $this->signerMois($this->prof->id);
    }

    private function genererPdf(): \Illuminate\Testing\TestResponse
    {
        $this->actingAsRole('directeur');

        return $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10]);
    }

    private function validee(): TimesheetVersion
    {
        return TimesheetVersion::where(['professeur_id' => $this->prof->id, 'type' => 'validee', 'statut' => 'active'])->firstOrFail();
    }

    public function test_signature_et_pdf_pointent_vers_la_version_validee(): void
    {
        $this->soumettreEtValider();

        $id = $this->signer()->assertOk()->json('signature.public_id');
        $this->genererPdf()->assertCreated();

        $sig = TimesheetSignature::where('public_id', $id)->firstOrFail();
        $validee = $this->validee();
        $this->assertSame($validee->id, $sig->version_id);
        $this->assertSame($validee->content_hash, $sig->version_hash);
        $payload = json_decode($sig->payload, true);
        $this->assertSame(2, $payload['version']);
        $this->assertSame($validee->id, $payload['version_timesheet_id']);
        $this->assertTrue(app(SignatureNumeriqueService::class)->sceauValide($sig));
        $this->assertSame($validee->id, TimesheetPdf::firstOrFail()->version_id);
    }

    public function test_un_contenu_qui_derive_de_la_validee_bloque_signature_et_pdf(): void
    {
        $this->soumettreEtValider();
        // Dérive hors des services (ex. correction directe en base) : la validée active ne correspond plus.
        Timesheet::whereKey($this->a->id)->update(['nombre_heures' => 1.0]);

        $r = $this->signer()->assertStatus(422);
        $this->assertStringContainsString('depuis sa validation', $r->json('message'));
        $this->assertSame(0, TimesheetSignature::count());
        Sanctum::actingAs($this->prof->user);
        $etat = $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk();
        $this->assertFalse($etat->json('peut_signer'));
        $this->assertStringContainsString('depuis sa validation', implode(' ', $etat->json('erreurs_signature')));
    }

    public function test_apres_une_adaptation_la_signature_precedente_est_perimee_et_la_nouvelle_vise_la_validee_v2(): void
    {
        $this->soumettreEtValider();
        $v1 = $this->validee();
        $premiere = $this->signer()->assertOk()->json('signature.public_id');
        $this->actingAsRole('directeur');
        $this->postJson("/api/timesheets/{$this->a->id}/adapter", ['nombre_heures' => 1.5, 'motif' => 'Plafond'])->assertOk();
        $v2 = $this->validee();

        $this->assertNotSame($v1->id, $v2->id);
        $this->assertTrue(app(SignatureNumeriqueService::class)->aResigner($this->prof, 2026, 10));
        $this->genererPdf()->assertStatus(422); // nouvelle signature requise, pas de PDF sur l'ancienne

        $seconde = $this->signer()->assertOk()->json('signature.public_id');
        $this->assertSame('remplacee', TimesheetSignature::where('public_id', $premiere)->first()->statut);
        $this->assertSame($v2->id, TimesheetSignature::where('public_id', $seconde)->first()->version_id);
        $this->genererPdf()->assertCreated();
        $this->assertSame($v2->id, TimesheetPdf::firstOrFail()->version_id);
    }

    public function test_un_mois_valide_avant_ts04_reste_signable_et_generable_sans_version(): void
    {
        $this->a->update(['statut_validation' => 'confirme']);
        $this->b->update(['statut_validation' => 'confirme']);

        $id = $this->signer()->assertOk()->json('signature.public_id');
        $this->genererPdf()->assertCreated();

        $sig = TimesheetSignature::where('public_id', $id)->firstOrFail();
        $this->assertNull($sig->version_id);
        $this->assertSame(1, json_decode($sig->payload, true)['version']);
        $this->assertNull(TimesheetPdf::firstOrFail()->version_id);
        $this->assertSame(0, TimesheetVersion::count());
    }

    public function test_le_drapeau_de_deploiement_desactive_la_garde(): void
    {
        config(['logiscool.garde_versions' => false]);
        $this->soumettreEtValider();
        Timesheet::whereKey($this->a->id)->update(['nombre_heures' => 1.0]);

        $sig = $this->signer()->assertOk()->json('signature.public_id');

        $this->assertNull(TimesheetSignature::where('public_id', $sig)->first()->version_id);
    }

    public function test_un_mois_valide_sans_lissage_malgre_un_depassement_du_plafond_reste_signable(): void
    {
        $this->b->update(['nombre_heures' => 3.0]); // 3 h × 20 € = 60 € le même jour : au-dessus du plafond journalier de 44,02 €
        $this->soumettreEtValider(); // validation SANS lissage

        Sanctum::actingAs($this->prof->user);
        $etat = $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk();
        $this->assertTrue($etat->json('peut_signer'));
        $this->assertSame([], $etat->json('erreurs_signature'));

        $this->signer()->assertOk();
        $this->genererPdf()->assertCreated();
    }

    public function test_un_mois_valide_avec_lissage_reste_signable(): void
    {
        $this->b->update(['nombre_heures' => 3.0]);
        Sanctum::actingAs($this->prof->user);
        $this->postJson('/api/timesheets/soumettre-mois', ['annee' => 2026, 'mois' => 10])->assertOk();
        $this->actingAsRole('directeur');
        $this->postJson('/api/timesheets/valider-lot', [
            'ids' => [$this->a->id, $this->b->id],
            'lissages' => [['timesheet_id' => $this->b->id, 'date_to' => '2026-10-08', 'montant_to_move' => 15.98]],
        ])->assertOk();

        $this->signer()->assertOk();
    }
}
