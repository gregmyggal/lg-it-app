<?php

namespace Tests\Feature;

use App\Models\ParametreSite;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\SignatureSpecimen;
use App\Models\Timesheet;
use App\Models\TimesheetPdf;
use App\Models\TimesheetSignature;
use App\Models\User;
use App\Services\SignatureCle;
use App\Services\SignatureNumeriqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** SIG-01 : signature du professeur, signature scellée du mois, bloc PDF, vérification publique, RGPD. */
class SignatureNumeriqueTest extends TestCase
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
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 11.75, 'date_debut' => '2026-01-01']);
    }

    private function ligne(string $date = '2026-10-10', float $h = 1, string $statut = 'confirme'): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $this->prof->id, 'date_prestation' => $date, 'nombre_heures' => $h,
            'type_activite' => 'animation', 'statut_validation' => $statut,
        ]);
    }

    private function enProf(): void
    {
        Sanctum::actingAs($this->prof->user);
    }

    /** Le professeur a une signature et signe un mois de deux lignes confirmées. */
    private function signe(): TimesheetSignature
    {
        $this->creerSignature($this->prof->user);
        $this->ligne('2026-10-01');
        $this->ligne('2026-10-08', 2);
        $this->enProf();
        $id = $this->signerMois($this->prof->id)->assertOk()->json('signature.public_id');

        return TimesheetSignature::where('public_id', $id)->firstOrFail();
    }

    public function test_le_professeur_enregistre_sa_signature_png_reencodee_et_la_relit(): void
    {
        $this->enProf();
        $image = 'data:image/png;base64,'.base64_encode($this->pngSignature()."\0charge-utile");

        $this->putJson('/api/ma-signature', ['type' => 'nom', 'texte' => 'Emma Roux', 'police' => 'Caveat', 'couleur' => '#1a3d8f', 'image' => $image, 'consentement' => true])
            ->assertOk()->assertJsonPath('data.type', 'nom')->assertJsonPath('data.police', 'Caveat');

        $s = SignatureSpecimen::where('user_id', $this->prof->user_id)->firstOrFail();
        $png = Storage::disk('local')->get($s->chemin);
        $this->assertStringNotContainsString('charge-utile', $png); // ré-encodée : seule l'image est gardée
        $this->assertSame(hash('sha256', $png), $s->sha256);
        $this->assertStringStartsWith('data:image/png;base64,', $this->getJson('/api/ma-signature')->assertOk()->json('data.image'));
    }

    public function test_une_signature_invalide_ou_sans_consentement_est_refusee(): void
    {
        $this->enProf();
        $png = 'data:image/png;base64,'.base64_encode($this->pngSignature());
        $base = ['type' => 'dessin', 'couleur' => '#1a3d8f', 'image' => $png, 'consentement' => true];

        $this->putJson('/api/ma-signature', ['consentement' => false] + $base)->assertStatus(422)->assertJsonValidationErrors('consentement');
        $this->putJson('/api/ma-signature', ['image' => 'data:image/jpeg;base64,AAAA'] + $base)->assertStatus(422)->assertJsonValidationErrors('image');
        $this->putJson('/api/ma-signature', ['image' => 'data:image/png;base64,'.base64_encode('pas une image')] + $base)->assertStatus(422);
        $this->putJson('/api/ma-signature', ['image' => 'data:image/png;base64,'.base64_encode($this->pngSignature(2500, 100))] + $base)->assertStatus(422);
        $this->putJson('/api/ma-signature', ['type' => 'nom', 'couleur' => '#ff0000'] + $base)->assertStatus(422)->assertJsonValidationErrors(['couleur', 'texte', 'police']);
        $this->putJson('/api/ma-signature', $base)->assertOk(); // dessin : ni texte ni police

        $this->actingAsRole('directeur');
        $this->getJson('/api/ma-signature')->assertForbidden();
    }

    public function test_signer_le_mois_cree_une_preuve_scellee_liee_au_contenu(): void
    {
        $sig = $this->signe();

        $this->assertSame(0, Timesheet::whereNull('signature_professeur')->count());
        $this->assertSame('127.0.0.1', $sig->ip);
        $this->assertMatchesRegularExpression('/^SIG-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $sig->public_id);
        $this->assertTrue(Storage::disk('local')->exists($sig->specimen_chemin));

        $service = app(SignatureNumeriqueService::class);
        $this->assertSame($service->empreinte($this->prof, 2026, 10), $sig->content_hash);
        $this->assertTrue($service->sceauValide($sig));
        $this->assertFalse($service->estPerimee($sig));
        $payload = json_decode($sig->payload, true);
        $this->assertSame($sig->content_hash, $payload['contenu_sha256']);
        $this->assertSame('127.0.0.1', $payload['ip']);

        // Toute retouche de la preuve en base casse le sceau.
        $sig->update(['payload' => str_replace('127.0.0.1', '10.0.0.1', $sig->payload)]);
        $this->assertFalse($service->sceauValide($sig->fresh()));
    }

    public function test_on_ne_signe_pas_sans_signature_enregistree_ni_sans_certification(): void
    {
        $this->ligne();
        $this->enProf();
        $this->signerMois($this->prof->id)->assertStatus(422)->assertJsonValidationErrors('signature');

        $this->creerSignature($this->prof->user);
        $this->postJson('/api/timesheets/sign-month', ['professeur_id' => $this->prof->id, 'year' => 2026, 'month' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('certification');
        $this->assertSame(0, TimesheetSignature::count());
    }

    public function test_la_direction_ne_signe_jamais_a_la_place_du_professeur(): void
    {
        $this->ligne();
        foreach (['directeur', 'admin'] as $role) {
            $user = $this->actingAsRole($role);
            $this->creerSignature($user);
            $this->signerMois($this->prof->id)->assertForbidden();
            SignatureSpecimen::where('user_id', $user->id)->delete();
        }
        $autre = Professeur::factory()->create();
        Sanctum::actingAs($autre->user);
        $this->signerMois($this->prof->id)->assertForbidden();
        $this->assertNull($this->ligne('2026-10-02')->fresh()->signature_professeur);
        $this->assertSame(0, TimesheetSignature::count());
    }

    public function test_un_ajustement_apres_signature_perime_la_signature_et_la_nouvelle_la_remplace(): void
    {
        $premiere = $this->signe();
        $ligne = Timesheet::where('date_prestation', '2026-10-08')->firstOrFail();
        $this->actingAsRole('directeur');
        $this->postJson("/api/timesheets/{$ligne->id}/adapter", ['nombre_heures' => 3, 'motif' => 'correction'])->assertOk();

        $detail = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk()->json();
        $this->assertTrue($detail['signature']['perimee']);

        $this->enProf();
        $etat = $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk()->json();
        $this->assertTrue($etat['signature']['perimee']);
        $this->assertTrue($etat['peut_signer']);
        $this->assertSame(2, $etat['recapitulatif']['lignes']);
        $this->assertEquals(4, $etat['recapitulatif']['heures']);
        $this->assertSame('BE68 •••• •••• 7034', $etat['recapitulatif']['compte_bancaire']);

        $nouvelle = $this->signerMois($this->prof->id)->assertOk()->json('signature.public_id');
        $this->assertSame(TimesheetSignature::STATUT_REMPLACEE, $premiere->fresh()->statut);
        $this->assertFalse(app(SignatureNumeriqueService::class)->estPerimee(TimesheetSignature::where('public_id', $nouvelle)->firstOrFail()));
    }

    public function test_un_changement_de_compte_bancaire_apres_signature_bloque_le_pdf_et_permet_de_resigner(): void
    {
        $this->signe();
        $this->prof->update(['compte_bancaire' => 'BE71096123456769']);

        $this->actingAsRole('directeur');
        $bloquants = $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->json('pdf.bloquants');
        $this->assertContains('Données modifiées depuis la signature : nouvelle signature du professeur requise', $bloquants);
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertStatus(422);

        $this->enProf();
        $this->signerMois($this->prof->id)->assertOk();
        $this->actingAsRole('directeur');
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated();
    }

    public function test_le_pdf_porte_la_signature_et_le_qr_sur_une_seule_page_avec_15_lignes(): void
    {
        ParametreSite::definir(ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE, true, User::factory()->admin()->create());
        $this->creerSignature($this->prof->user);
        for ($j = 1; $j <= 15; $j++) {
            $this->ligne(sprintf('2026-10-%02d', $j));
        }
        $this->enProf();
        $this->signerMois($this->prof->id)->assertOk();

        $this->actingAsRole('directeur');
        $id = $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated()->json('data.id');
        $pdf = Storage::disk('local')->get(TimesheetPdf::findOrFail($id)->chemin);

        $this->assertSame(1, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf), 'La fiche de 15 lignes doit tenir sur une page');
        $this->assertGreaterThanOrEqual(1, preg_match_all('#/Subtype\s*/Image#', $pdf), 'Image de signature attendue');
    }

    public function test_la_verification_publique_n_existe_que_pour_les_signatures_emises_avec_le_parametre(): void
    {
        $sans = $this->signe();
        $this->getJson("/api/signatures/{$sans->public_id}/verification")->assertNotFound();

        $this->actingAsRole('directeur');
        $this->putJson('/api/signature-parametres', ['verification_publique' => true])->assertOk()->assertJsonPath('data.verification_publique', true);
        $this->getJson("/api/signatures/{$sans->public_id}/verification")->assertNotFound(); // émise avant activation

        $this->prof->update(['compte_bancaire' => 'BE71096123456769']);
        $this->enProf();
        $avec = $this->signerMois($this->prof->id)->assertOk()->json('signature.public_id');

        $this->app['auth']->forgetGuards();
        $j = $this->getJson('/api/signatures/'.strtolower($avec).'/verification')->assertOk()->json('data');
        $this->assertSame('valide', $j['statut']);
        $this->assertSame('Fiche de défraiement – octobre 2026', $j['document']);
        $this->assertArrayNotHasKey('ip', $j);
        $this->assertStringNotContainsString('BE71', json_encode($j));
        $this->assertStringNotContainsString($this->prof->nom, json_encode($j));
        $this->getJson('/api/signatures/SIG-0000-0000/verification')->assertNotFound();
    }

    public function test_seul_le_staff_modifie_le_parametre_de_verification(): void
    {
        $this->enProf();
        $this->putJson('/api/signature-parametres', ['verification_publique' => true])->assertForbidden();
        $this->assertFalse(ParametreSite::booleen(ParametreSite::SIGNATURE_VERIFICATION_PUBLIQUE));
    }

    public function test_la_preuve_masque_l_ip_pour_le_directeur_et_la_montre_a_l_admin(): void
    {
        $sig = $this->signe();
        $sig->update(['ip' => '81.242.17.203']);
        $url = "/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10";

        $this->actingAsRole('directeur');
        $this->getJson($url)->assertOk()->assertJsonPath('signature.ip', '81.242.x.x')->assertJsonPath('signature.public_id', $sig->public_id);
        $this->actingAsRole('admin');
        $this->getJson($url)->assertOk()->assertJsonPath('signature.ip', '81.242.17.203');
    }

    public function test_les_preuves_de_plus_de_7_ans_sont_anonymisees(): void
    {
        $sig = $this->signe();
        $recente = $sig->replicate(['public_id'])->fill(['public_id' => 'SIG-AAAA-BBBB']);
        $recente->save();
        $sig->update(['signed_at' => now()->subYears(7)->subDay()]);

        $this->artisan('signatures:anonymiser')->assertSuccessful();

        $sig->refresh();
        $this->assertNull($sig->ip);
        $this->assertNull($sig->payload);
        $this->assertNotNull($sig->anonymisee_at);
        $this->assertNull(app(SignatureNumeriqueService::class)->sceauValide($sig));
        $this->assertNotNull($recente->fresh()->ip);
    }

    public function test_la_cle_configuree_scelle_et_une_ancienne_cle_publique_verifie_encore(): void
    {
        $ancienne = SignatureCle::generer();
        config(['signature.cle_privee' => $ancienne['privee'], 'signature.cle_id' => 'cle-a']);
        $sceau = app(SignatureCle::class)->sceller('message');
        $this->assertTrue(app(SignatureCle::class)->verifier('message', $sceau, 'cle-a'));

        config(['signature.cle_privee' => SignatureCle::generer()['privee'], 'signature.cle_id' => 'cle-b', 'signature.cles_publiques' => ['cle-a' => $ancienne['publique']]]);
        $this->assertTrue(app(SignatureCle::class)->verifier('message', $sceau, 'cle-a'));
        $this->assertFalse(app(SignatureCle::class)->verifier('autre', $sceau, 'cle-a'));
        $this->assertFalse(app(SignatureCle::class)->verifier('message', $sceau, 'inconnue'));
    }
}
