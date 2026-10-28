<?php

namespace Tests\Feature;

use App\Models\Employeur;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetSignature;
use App\Services\SignatureNumeriqueService;
use App\Services\TimesheetPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** Paramètre « bénévoles » d'une entité employeur : vocabulaire des fiches, de la signature et de la vérification publique. */
class EmployeurBenevolesTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;
    use SigneMois;

    private Professeur $prof;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 20, 'date_debut' => '2026-01-01']);
        Employeur::amorcer();
    }

    private function termes(Employeur $e): array
    {
        $m = new ReflectionMethod(TimesheetPdfService::class, 'termes');
        $m->setAccessible(true);

        return $m->invoke(app(TimesheetPdfService::class), $e);
    }

    public function test_les_entites_sont_benevoles_par_defaut(): void
    {
        $this->assertTrue(Employeur::where('code', 'asbl')->firstOrFail()->benevoles);
        $this->assertTrue(Employeur::where('code', 'lit_solutions')->firstOrFail()->benevoles);
    }

    public function test_l_admin_regle_le_parametre_a_la_creation_et_a_la_modification(): void
    {
        $this->actingAsRole('admin');
        $payload = ['code' => 'ma_sprl', 'nom' => 'Ma SPRL', 'rpm' => 'BE0123.456.789', 'compte_bancaire' => 'BE68539007547034', 'adresse' => 'Rue 1, 1000 Bruxelles'];

        $id = $this->postJson('/api/employeurs', $payload + ['benevoles' => false])->assertCreated()->assertJsonPath('data.benevoles', false)->json('data.id');
        $this->putJson("/api/employeurs/{$id}", ['benevoles' => true])->assertOk()->assertJsonPath('data.benevoles', true);
        $this->postJson('/api/employeurs', $payload)->assertStatus(422); // code déjà pris : contrôle intact
    }

    public function test_le_vocabulaire_de_la_fiche_suit_l_entite(): void
    {
        $benevoles = Employeur::where('code', 'asbl')->firstOrFail();
        $employes = tap(Employeur::where('code', 'lit_solutions')->firstOrFail())->update(['benevoles' => false]);

        $b = $this->termes($benevoles);
        $this->assertStringContainsString('VOLONTARIAT', $b['titre']);
        $this->assertStringContainsString('volontaire', $b['nom'].$b['signature'].$b['par']);

        $e = $this->termes($employes->fresh());
        $this->assertSame('FICHE DE DÉFRAIEMENT', $e['titre']);
        foreach ($e as $texte) {
            $this->assertStringNotContainsStringIgnoringCase('volontaire', $texte);
            $this->assertStringNotContainsStringIgnoringCase('bénévole', $texte);
        }
    }

    public function test_le_professeur_voit_le_vocabulaire_de_son_employeur(): void
    {
        Employeur::where('code', 'asbl')->update(['benevoles' => false]);
        Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => '2026-10-05', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'confirme']);
        Sanctum::actingAs($this->prof->user);

        $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk()->assertJsonPath('employeur.benevoles', false);
        $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->assertJsonPath('employeur.benevoles', false);
    }

    public function test_la_verification_publique_suit_l_employeur_de_la_signature(): void
    {
        $this->creerSignature($this->prof->user);
        Timesheet::create(['professeur_id' => $this->prof->id, 'date_prestation' => '2026-10-05', 'nombre_heures' => 1, 'type_activite' => 'animation', 'statut_validation' => 'confirme']);
        \App\Models\ParametreSite::definir(\App\Models\ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE, true, $this->actingAsRole('admin'));
        Sanctum::actingAs($this->prof->user);
        $id = $this->signerMois($this->prof->id)->assertOk()->json('signature.public_id');
        $service = app(SignatureNumeriqueService::class);

        $this->assertTrue($service->verificationPublique($id)['benevoles']);

        Employeur::where('code', 'asbl')->update(['benevoles' => false]);
        $this->assertFalse($service->verificationPublique($id)['benevoles']);
        $this->assertSame(1, TimesheetSignature::count());
    }
}
