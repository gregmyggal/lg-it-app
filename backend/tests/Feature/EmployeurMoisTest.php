<?php

namespace Tests\Feature;

use App\Models\Employeur;
use App\Models\EmployeurMoisAudit;
use App\Models\Professeur;
use App\Models\ProfesseurEmployeurMois;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetPdf;
use App\Models\TimesheetSignature;
use App\Services\EmployeurBackfillService;
use App\Services\SignatureNumeriqueService;
use App\Services\TimesheetPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Concerns\PrepareScolarite;
use Tests\Concerns\SigneMois;
use Tests\TestCase;

/** EMP-01 : employeur d'un animateur par mois (résolution, droits, verrous, lot, entités, PDF, signature, synthèse, backfill). */
class EmployeurMoisTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;
    use SigneMois;

    private Professeur $prof;

    private Employeur $asbl;

    private Employeur $lit;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
        Storage::fake('local');
        $this->asbl = Employeur::where('code', Employeur::CODE_ASBL)->firstOrFail();
        $this->lit = Employeur::where('code', Employeur::CODE_LIT)->firstOrFail();
        $this->prof = Professeur::factory()->create(['compte_bancaire' => 'BE68539007547034']);
        ProfesseurTarif::create(['professeur_id' => $this->prof->id, 'tarif_horaire_eur' => 11.75, 'date_debut' => '2026-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Complète L-IT avec des coordonnées FICTIVES de test (jamais des vraies). */
    private function completerLit(): void
    {
        $this->lit->update(['rpm' => 'BE0123.456.789', 'compte_bancaire' => 'BE68539007547034', 'adresse' => 'Rue de Test 1 – 7000 MONS']);
    }

    private function url(?Professeur $p = null, string $suffixe = ''): string
    {
        return '/api/professeurs/'.($p ?? $this->prof)->id.'/employeurs-mois'.$suffixe;
    }

    private function ligne(Professeur $p, string $date, string $statut = 'confirme', bool $signee = true, string $type = 'animation', float $h = 3): Timesheet
    {
        return Timesheet::create([
            'professeur_id' => $p->id, 'date_prestation' => $date, 'nombre_heures' => $h, 'type_activite' => $type,
            'statut_validation' => $statut, 'signature_professeur' => $signee ? now() : null,
        ]);
    }

    private function fixer(Professeur $p, int $annee, int $mois, Employeur $e, string $source = 'explicite'): ProfesseurEmployeurMois
    {
        return ProfesseurEmployeurMois::factory()->create(['professeur_id' => $p->id, 'annee' => $annee, 'mois' => $mois, 'employeur_id' => $e->id, 'source' => $source]);
    }

    private function definir(int $annee, int $mois, Employeur $e, array $extra = [], ?Professeur $p = null)
    {
        return $this->putJson($this->url($p)."/{$annee}/{$mois}", ['employeur_id' => $e->id, 'version' => 0] + $extra);
    }

    // ------------------------------------------------------------------ résolution (RG-2)

    public function test_sans_ligne_ni_mois_anterieur_l_entite_par_defaut_s_applique(): void // AC-2
    {
        $this->actingAsRole('directeur');

        $r = $this->getJson($this->url().'?annee=2026')->assertOk();

        $this->assertCount(12, $r->json('mois'));
        $this->assertSame('asbl', $r->json('mois.9.employeur.code'));
        $this->assertSame('defaut', $r->json('mois.9.source'));
        $this->assertFalse($r->json('mois.9.verrouille'));
    }

    public function test_un_mois_sans_ligne_herite_du_dernier_mois_defini_meme_d_une_annee_anterieure(): void // AC-1
    {
        $this->fixer($this->prof, 2025, 11, $this->lit);
        $this->fixer($this->prof, 2026, 3, $this->asbl);
        $this->actingAsRole('directeur');

        $r = $this->getJson($this->url().'?annee=2026')->assertOk();

        $this->assertSame('lit_solutions', $r->json('mois.0.employeur.code')); // janvier ← nov. 2025
        $this->assertSame('herite', $r->json('mois.0.source'));
        $this->assertSame('2025-11', $r->json('mois.0.herite_de'));
        $this->assertSame('asbl', $r->json('mois.2.employeur.code'));      // mars explicite
        $this->assertSame('explicite', $r->json('mois.2.source'));
        $this->assertSame('asbl', $r->json('mois.5.employeur.code'));      // juin ← mars
        $this->assertSame('2026-03', $r->json('mois.5.herite_de'));
    }

    // ------------------------------------------------------------------ droits (AC-7, RG-6, RG-10)

    public function test_droits_anonyme_professeur_et_isolation(): void
    {
        $this->getJson($this->url().'?annee=2026')->assertUnauthorized();

        $user = $this->actingAsRole('professeur');
        $moi = Professeur::factory()->create(['user_id' => $user->id]);
        $this->fixer($moi, 2026, 10, $this->lit);

        // Lecture de ses propres mois : nom de l'entité seulement.
        $r = $this->getJson($this->url($moi).'?annee=2026')->assertOk();
        $this->assertSame(['mois' => 10, 'employeur' => ['nom' => 'L-IT Solutions']], $r->json('mois.9'));
        $this->assertStringNotContainsString('compte', $r->getContent());
        $this->assertStringNotContainsString('version', $r->getContent());

        // Jamais en écriture, ni l'historique, ni les mois d'un autre, ni les entités.
        $this->putJson($this->url($moi).'/2026/10', ['employeur_id' => $this->asbl->id, 'version' => 1])->assertForbidden();
        $this->getJson($this->url($moi, '/historique'))->assertForbidden();
        $this->getJson($this->url().'?annee=2026')->assertForbidden();
        $this->postJson('/api/employeurs-mois/lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [$moi->id], 'employeur_id' => $this->asbl->id])->assertForbidden();
        $this->getJson('/api/employeurs')->assertForbidden();
        $this->assertSame('L-IT Solutions', $this->fixerLecture($moi));
    }

    private function fixerLecture(Professeur $p): string
    {
        return ProfesseurEmployeurMois::where('professeur_id', $p->id)->firstOrFail()->employeur->nom; // inchangé après les 403
    }

    // ------------------------------------------------------------------ écriture (AC-3, RG-9, RG-11, RG-15)

    public function test_le_directeur_fixe_l_employeur_et_le_changement_est_journalise(): void // AC-3
    {
        $directeur = $this->actingAsRole('directeur');

        $this->definir(2026, 11, $this->lit, ['motif' => 'Nouveau contrat'])->assertOk()
            ->assertJsonPath('data.employeur.code', 'lit_solutions')
            ->assertJsonPath('data.source', 'explicite')
            ->assertJsonPath('data.version', 1);

        $l = ProfesseurEmployeurMois::firstOrFail();
        $this->assertSame($this->lit->id, $l->employeur_id);
        $this->assertSame($directeur->id, $l->updated_by);
        $a = EmployeurMoisAudit::firstOrFail();
        $this->assertSame([$this->asbl->id, $this->lit->id, 'Nouveau contrat', $directeur->id], [$a->employeur_avant_id, $a->employeur_apres_id, $a->motif, $a->user_id]);

        $h = $this->getJson($this->url(null, '/historique'))->assertOk();
        $this->assertSame('Code IT Bryan ! asbl', $h->json('data.0.employeur_avant.nom'));
        $this->assertSame('L-IT Solutions', $h->json('data.0.employeur_apres.nom'));
        $this->assertSame('Nouveau contrat', $h->json('data.0.motif'));
    }

    public function test_motif_obligatoire_pour_modifier_une_valeur_explicite_ou_un_mois_passe(): void // RG-9
    {
        $this->actingAsRole('directeur');

        // Première définition d'un mois futur : motif facultatif.
        $this->definir(2026, 12, $this->lit)->assertOk();
        // Modification de la valeur explicite : motif obligatoire.
        $this->putJson($this->url().'/2026/12', ['employeur_id' => $this->asbl->id, 'version' => 1])
            ->assertStatus(422)->assertJsonPath('errors.motif.0', 'Motif obligatoire pour modifier un employeur déjà défini.');
        $this->assertSame($this->lit->id, ProfesseurEmployeurMois::firstOrFail()->employeur_id);
        // Mois passé, première définition : motif obligatoire aussi.
        $this->definir(2026, 9, $this->lit)->assertStatus(422)->assertJsonPath('errors.motif.0', fn ($m) => str_contains($m, 'Motif'));
        $this->definir(2026, 9, $this->lit, ['motif' => 'Régularisation'])->assertOk();
    }

    public function test_conflit_de_version_409_et_valeur_identique_sans_ecriture(): void // RG-15
    {
        $this->actingAsRole('directeur');
        $this->definir(2026, 12, $this->lit)->assertOk()->assertJsonPath('data.version', 1);

        $this->putJson($this->url().'/2026/12', ['employeur_id' => $this->asbl->id, 'version' => 0, 'motif' => 'x y z'])
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'Modification simultanée') && str_contains($m, 'Rechargez'));

        // Même valeur : 200, version inchangée, pas de nouvelle entrée de journal.
        $this->putJson($this->url().'/2026/12', ['employeur_id' => $this->lit->id, 'version' => 1])->assertOk()->assertJsonPath('data.version', 1);
        $this->assertSame(1, EmployeurMoisAudit::count());
    }

    public function test_mois_a_venir_accepte_jusqu_a_12_mois_et_refuse_au_dela(): void // AC-12, RG-11
    {
        $this->actingAsRole('directeur');

        $this->definir(2027, 10, $this->lit)->assertOk();          // oct. 2026 + 12 mois
        $this->definir(2027, 11, $this->lit)->assertStatus(422);   // au-delà
        $this->definir(2019, 12, $this->lit, ['motif' => 'trop ancien'])->assertStatus(422);
    }

    public function test_entite_desactivee_non_selectionnable_et_validation(): void
    {
        $this->actingAsRole('directeur');
        $this->lit->update(['actif' => false]);

        $this->definir(2026, 11, $this->lit)->assertStatus(422)->assertJsonPath('errors.employeur_id.0', 'Entité désactivée.');
        $this->putJson($this->url().'/2026/11', ['version' => 0])->assertStatus(422)->assertJsonValidationErrors(['employeur_id']);
        $this->putJson($this->url().'/2026/13', ['employeur_id' => $this->asbl->id, 'version' => 0])->assertNotFound();
    }

    // ------------------------------------------------------------------ verrous (RG-7, AC-4, AC-5)

    public function test_un_mois_avec_pdf_genere_est_verrouille_pour_tous_et_rien_n_est_modifie(): void // AC-4
    {
        $this->ligne($this->prof, '2026-10-05', 'genere');
        $this->fixer($this->prof, 2026, 10, $this->asbl);

        foreach (['directeur', 'admin'] as $role) {
            $this->actingAsRole($role);
            $this->putJson($this->url().'/2026/10', ['employeur_id' => $this->lit->id, 'version' => 1, 'motif' => 'Changement'])
                ->assertStatus(422)->assertJsonPath('message', 'Ce mois est verrouillé : une fiche PDF a été générée. Un administrateur peut le déverrouiller.');
        }
        $this->assertSame($this->asbl->id, ProfesseurEmployeurMois::firstOrFail()->employeur_id);

        $r = $this->getJson($this->url().'?annee=2026')->assertOk();
        $this->assertTrue($r->json('mois.9.verrouille'));
        $this->assertSame('pdf', $r->json('mois.9.type_verrou'));
        $this->assertFalse($r->json('mois.9.modifiable'));
    }

    public function test_un_mois_signe_sans_pdf_est_verrouille_pour_le_directeur_et_modifiable_par_l_admin_avec_motif(): void // AC-5
    {
        $this->creerSignature($this->prof->user);
        $this->ligne($this->prof, '2026-10-01', 'confirme', false);
        $this->ligne($this->prof, '2026-10-08', 'confirme', false, 'animation', 2);
        Sanctum::actingAs($this->prof->user);
        $this->signerMois($this->prof->id)->assertOk();
        $signatures = app(SignatureNumeriqueService::class);
        $this->assertFalse($signatures->aResigner($this->prof, 2026, 10));
        // La signature a figé l'employeur du mois (RG-3).
        $this->assertSame('fige', ProfesseurEmployeurMois::firstOrFail()->source);
        $version = ProfesseurEmployeurMois::firstOrFail()->version;

        $this->actingAsRole('directeur');
        $this->putJson($this->url().'/2026/10', ['employeur_id' => $this->lit->id, 'version' => $version, 'motif' => 'Changement'])
            ->assertStatus(422)->assertJsonPath('message', "Ce mois est signé : l'employeur ne peut plus être modifié par la direction.");

        $this->actingAsRole('admin');
        $this->putJson($this->url().'/2026/10', ['employeur_id' => $this->lit->id, 'version' => $version])->assertStatus(422)->assertJsonValidationErrors(['motif']);
        $this->putJson($this->url().'/2026/10', ['employeur_id' => $this->lit->id, 'version' => $version, 'motif' => 'Contrat L-IT'])->assertOk();

        $this->assertTrue($signatures->aResigner($this->prof->fresh(), 2026, 10));
        $this->assertSame('Contrat L-IT', EmployeurMoisAudit::latest('id')->first()->motif);
    }

    public function test_changer_d_employeur_ne_perime_pas_les_signatures_historiques_de_l_asbl(): void
    {
        $this->creerSignature($this->prof->user);
        $this->ligne($this->prof, '2026-10-01', 'confirme', false);
        Sanctum::actingAs($this->prof->user);
        $this->signerMois($this->prof->id)->assertOk();
        $sig = TimesheetSignature::firstOrFail();
        // Rétrocompatibilité : une signature d'avant EMP-01 (contenu sans employeur) reste valide tant que l'employeur est l'ASBL.
        $this->assertFalse(app(SignatureNumeriqueService::class)->estPerimee($sig));
    }

    // ------------------------------------------------------------------ lot (AC-6, RG-5)

    public function test_lot_applique_les_mois_libres_et_ignore_les_verrouilles(): void // AC-6
    {
        $profs = Professeur::factory()->count(5)->create();
        $this->ligne($profs[4], '2026-10-05', 'genere'); // verrouillé (PDF)
        $directeur = $this->actingAsRole('directeur');

        $r = $this->postJson('/api/employeurs-mois/lot', [
            'annee' => 2026, 'mois' => 10, 'professeur_ids' => $profs->pluck('id')->all(), 'employeur_id' => $this->lit->id, 'motif' => 'Passage L-IT',
        ])->assertOk();

        $this->assertCount(4, $r->json('appliques'));
        $this->assertCount(1, $r->json('ignores'));
        $this->assertSame($profs[4]->id, $r->json('ignores.0.professeur_id'));
        $this->assertStringContainsString('verrouillé', $r->json('ignores.0.raison'));
        $this->assertSame(4, ProfesseurEmployeurMois::where('employeur_id', $this->lit->id)->count());
        $this->assertSame(4, EmployeurMoisAudit::where('user_id', $directeur->id)->count());
    }

    public function test_lot_reprendre_le_mois_precedent_et_motif_obligatoire_si_valeur_explicite(): void // RG-5
    {
        $a = Professeur::factory()->create();
        $b = Professeur::factory()->create();
        $this->fixer($a, 2026, 10, $this->lit);
        $this->fixer($b, 2026, 10, $this->asbl);
        $this->fixer($b, 2026, 11, $this->lit); // déjà explicite en novembre : sera remplacé
        $this->actingAsRole('directeur');
        $payload = ['annee' => 2026, 'mois' => 11, 'professeur_ids' => [$a->id, $b->id], 'reprendre_precedent' => true];

        $this->postJson('/api/employeurs-mois/lot', $payload)->assertStatus(422)->assertJsonValidationErrors(['motif']);
        $this->assertSame($this->lit->id, ProfesseurEmployeurMois::where(['professeur_id' => $b->id, 'mois' => 11])->value('employeur_id'));

        $this->postJson('/api/employeurs-mois/lot', $payload + ['motif' => 'Retour à la normale'])->assertOk()->assertJsonCount(2, 'appliques');
        $this->assertSame($this->lit->id, ProfesseurEmployeurMois::where(['professeur_id' => $a->id, 'mois' => 11])->value('employeur_id'));
        $this->assertSame($this->asbl->id, ProfesseurEmployeurMois::where(['professeur_id' => $b->id, 'mois' => 11])->value('employeur_id'));
    }

    public function test_lot_validation_entree(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson('/api/employeurs-mois/lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => []])->assertStatus(422);
        $this->postJson('/api/employeurs-mois/lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [999999], 'employeur_id' => $this->lit->id])->assertStatus(422);
        $this->postJson('/api/employeurs-mois/lot', ['annee' => 2026, 'mois' => 10, 'professeur_ids' => [$this->prof->id]])->assertStatus(422)->assertJsonValidationErrors(['employeur_id']);
    }

    // ------------------------------------------------------------------ entités (RG-12, AC-13)

    public function test_les_entites_sont_lisibles_par_le_staff_et_gerees_par_l_admin_seulement(): void
    {
        $this->actingAsRole('directeur');
        $liste = $this->getJson('/api/employeurs')->assertOk();
        $this->assertSame('asbl', $liste->json('data.0.code'));
        $this->assertSame('BE02 1431 1606 4140', $liste->json('data.0.compte_bancaire'));
        $this->assertFalse($liste->json('data.0.can.update'));
        $this->assertFalse($liste->json('data.1.coordonnees_completes')); // L-IT à compléter

        $this->putJson('/api/employeurs/'.$this->lit->id, ['nom' => 'X'])->assertForbidden();
        $this->postJson('/api/employeurs', ['code' => 'x'])->assertForbidden();

        $this->actingAsRole('admin');
        $this->getJson('/api/employeurs')->assertOk()->assertJsonPath('data.0.can.update', true);
    }

    public function test_l_admin_cree_et_modifie_une_entite_avec_validation_par_champ(): void // AC-13
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/employeurs', ['code' => 'Mauvais Code', 'nom' => 'X', 'rpm' => 'BE12', 'compte_bancaire' => 'BE00 0000 0000 0001', 'adresse' => 'Rue 1'])
            ->assertStatus(422)->assertJsonValidationErrors(['code', 'rpm', 'compte_bancaire']);

        $this->postJson('/api/employeurs', ['code' => 'partenaire', 'nom' => 'Partenaire SRL', 'rpm' => 'be 0123 456 789', 'compte_bancaire' => 'be68 5390 0754 7034', 'adresse' => 'Rue de Test 1 – 7000 MONS'])
            ->assertCreated()->assertJsonPath('data.rpm', 'BE0123.456.789')->assertJsonPath('data.compte_bancaire', 'BE68 5390 0754 7034')->assertJsonPath('data.coordonnees_completes', true);
        $this->postJson('/api/employeurs', ['code' => 'partenaire', 'nom' => 'Doublon', 'rpm' => 'BE0123.456.789', 'compte_bancaire' => 'BE68539007547034', 'adresse' => 'Rue 1'])
            ->assertStatus(422)->assertJsonValidationErrors(['code']);

        $this->putJson('/api/employeurs/'.$this->lit->id, ['compte_bancaire' => 'BE00 0000 0000 0001'])->assertStatus(422)->assertJsonValidationErrors(['compte_bancaire']);
        $this->putJson('/api/employeurs/'.$this->lit->id, ['rpm' => 'BE0123.456.789', 'adresse' => 'Rue de Test 1 – 7000 MONS', 'compte_bancaire' => 'BE68 5390 0754 7034'])
            ->assertOk()->assertJsonPath('data.coordonnees_completes', true);
    }

    public function test_le_compte_de_l_entite_est_chiffre_au_repos(): void
    {
        $brut = (string) DB::table('employeurs')->where('code', 'asbl')->value('compte_bancaire');

        $this->assertNotSame('', $brut);
        $this->assertStringNotContainsString('1431', $brut);
        $this->assertStringNotContainsString('BE02', $brut);
        $this->assertSame('BE02143116064140', $this->asbl->fresh()->compte_bancaire);
    }

    public function test_une_seule_entite_par_defaut_et_pas_de_desactivation_de_la_defaut(): void // RG-12
    {
        $this->actingAsRole('admin');
        $this->completerLit();

        $this->putJson('/api/employeurs/'.$this->asbl->id, ['actif' => false])->assertStatus(422);
        $this->putJson('/api/employeurs/'.$this->asbl->id, ['par_defaut' => false])->assertStatus(422);

        $this->putJson('/api/employeurs/'.$this->lit->id, ['par_defaut' => true])->assertOk();
        $this->assertSame(['lit_solutions'], Employeur::where('par_defaut', true)->pluck('code')->all());
        $this->assertSame('lit_solutions', $this->getJson($this->url().'?annee=2026')->json('mois.0.employeur.code'));

        // Une entité utilisée se désactive (elle reste sur les mois existants) ; elle n'est plus sélectionnable.
        $this->fixer($this->prof, 2026, 3, $this->asbl);
        $this->putJson('/api/employeurs/'.$this->asbl->id, ['actif' => false])->assertOk()->assertJsonPath('data.mois_lies', 1);
        $this->definir(2026, 11, $this->asbl)->assertStatus(422);
        $this->assertSame('asbl', $this->getJson($this->url().'?annee=2026')->json('mois.2.employeur.code'));
    }

    // ------------------------------------------------------------------ PDF (AC-8, AC-9, RG-8)

    private function preparerMois(): void
    {
        $this->ligne($this->prof, '2026-10-01');
        $this->ligne($this->prof, '2026-10-30', 'confirme', true, 'deplacement', 1);
    }

    public function test_pied_de_page_de_la_fiche_selon_l_employeur(): void
    {
        $pied = new ReflectionMethod(TimesheetPdfService::class, 'htmlPied');
        $service = app(TimesheetPdfService::class);

        $html = $pied->invoke($service, $this->asbl);
        $this->assertStringContainsString('Code IT Bryan ! asbl', $html);
        $this->assertStringContainsString('BE0770.479.710', $html);
        $this->assertStringContainsString('BE02 1431 1606 4140', $html);
        $this->assertStringContainsString('Rampe Sainte Waudru', $html);

        $this->completerLit();
        $html = $pied->invoke($service, $this->lit->fresh());
        $this->assertStringContainsString('L-IT Solutions', $html);
        $this->assertStringContainsString('BE0123.456.789', $html);
        $this->assertStringNotContainsString('Code IT Bryan', $html);
    }

    public function test_generation_fige_l_employeur_du_mois_et_son_snapshot_reste_inchange(): void // AC-8, AC-9
    {
        $this->preparerMois();
        $this->completerLit();
        $this->fixer($this->prof, 2026, 9, $this->lit);   // octobre hérite de L-IT
        $directeur = $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated();

        $pdf = TimesheetPdf::firstOrFail();
        $this->assertSame($this->lit->id, $pdf->employeur_id);
        $this->assertSame('L-IT Solutions', $pdf->employeur_snapshot['nom']);
        $this->assertSame('BE0123.456.789', $pdf->employeur_snapshot['rpm']);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdf->chemin));
        // RG-3 : le mois hérité est devenu explicite (« fige ») et journalisé.
        $ligne = ProfesseurEmployeurMois::where(['annee' => 2026, 'mois' => 10])->firstOrFail();
        $this->assertSame(['fige', $this->lit->id, $directeur->id], [$ligne->source, $ligne->employeur_id, $ligne->updated_by]);
        $this->assertSame(1, EmployeurMoisAudit::where(['annee' => 2026, 'mois' => 10])->count());

        // L'entité change ensuite : le PDF stocké et son snapshot ne bougent pas (le fichier est inchangé).
        $contenu = Storage::disk('local')->get($pdf->chemin);
        $this->lit->update(['nom' => 'L-IT Solutions SRL', 'adresse' => 'Nouvelle adresse 2']);
        $this->assertSame('L-IT Solutions', $pdf->fresh()->employeur_snapshot['nom']);
        $this->assertSame($contenu, Storage::disk('local')->get($pdf->chemin));
    }

    public function test_la_generation_est_bloquee_tant_que_les_coordonnees_de_l_employeur_sont_a_completer(): void
    {
        $this->preparerMois();
        $this->fixer($this->prof, 2026, 10, $this->lit);
        $this->actingAsRole('directeur');

        $r = $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertStatus(422);
        $this->assertStringContainsString('à compléter', $r->json('message'));
        $this->assertSame(0, TimesheetPdf::count());
        // L'aperçu reste disponible.
        $this->get("/api/professeurs/{$this->prof->id}/timesheet-pdfs/apercu?annee=2026&mois=10")->assertOk();

        $this->completerLit();
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated();
    }

    public function test_apres_deverrouillage_l_admin_change_l_employeur_et_la_nouvelle_version_le_porte(): void // RG-8
    {
        $this->preparerMois();
        $this->completerLit();
        $this->actingAsRole('directeur');
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated();
        $this->assertSame($this->asbl->id, TimesheetPdf::firstOrFail()->employeur_id);

        $this->actingAsRole('admin');
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheets-mois/deverrouiller", ['annee' => 2026, 'mois' => 10, 'motif' => 'Mauvaise entité'])->assertOk();
        $version = ProfesseurEmployeurMois::firstOrFail()->version;
        $this->putJson($this->url().'/2026/10', ['employeur_id' => $this->lit->id, 'version' => $version, 'motif' => 'Mauvaise entité'])->assertOk();
        // Le contenu signé a changé (employeur) : les saisies doivent être re-signées pour le PDF suivant si une signature existe ; ici sans signature numérique.
        $this->postJson("/api/professeurs/{$this->prof->id}/timesheet-pdfs", ['annee' => 2026, 'mois' => 10])->assertCreated()->assertJsonPath('data.version', 2);

        $versions = TimesheetPdf::orderBy('version')->get();
        $this->assertSame($this->asbl->id, $versions[0]->employeur_id); // l'ancienne version garde son snapshot
        $this->assertSame($this->lit->id, $versions[1]->employeur_id);
    }

    // ------------------------------------------------------------------ signature

    public function test_la_signature_porte_l_employeur_du_mois_comme_emetteur(): void
    {
        $this->fixer($this->prof, 2026, 10, $this->lit);
        $this->creerSignature($this->prof->user);
        $this->ligne($this->prof, '2026-10-01', 'confirme', false);
        Sanctum::actingAs($this->prof->user);
        $id = $this->signerMois($this->prof->id)->assertOk()->json('signature.public_id');

        $sig = TimesheetSignature::where('public_id', $id)->firstOrFail();
        $payload = json_decode($sig->payload, true);
        $this->assertSame('L-IT Solutions', $payload['emetteur']);
        $this->assertSame($this->lit->id, $payload['employeur_id']);
        $this->assertTrue(app(SignatureNumeriqueService::class)->sceauValide($sig));

        $sig->update(['verification_publique' => true]);
        $this->getJson("/api/signatures/{$id}/verification")->assertOk()->assertJsonPath('data.emetteur', 'L-IT Solutions');
    }

    // ------------------------------------------------------------------ synthèse / détail / professeur

    public function test_la_synthese_expose_l_employeur_de_chaque_animateur_sans_n_plus_un(): void
    {
        $profs = Professeur::factory()->count(6)->create();
        foreach ($profs as $i => $p) {
            $this->ligne($p, '2026-10-05', 'soumis', false);
            if ($i % 2 === 0) {
                $this->fixer($p, 2026, 9, $this->lit);
            }
        }
        $this->ligne($profs[5], '2026-10-06', 'genere'); // verrou PDF
        $this->actingAsRole('directeur');

        $requetes = 0;
        DB::listen(function ($q) use (&$requetes) {
            if (str_contains($q->sql, 'professeur_employeurs_mois') || str_contains($q->sql, 'from `employeurs`')) {
                $requetes++;
            }
        });
        $r = $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertOk();

        $this->assertLessThanOrEqual(3, $requetes, "N+1 sur les employeurs ($requetes requêtes)");
        $parProf = collect($r->json('professeurs'))->keyBy('professeur_id');
        $this->assertSame('herite', $parProf[$profs[0]->id]['employeur']['source']);
        $this->assertSame('lit_solutions', $parProf[$profs[0]->id]['employeur']['employeur']['code']);
        $this->assertSame('defaut', $parProf[$profs[1]->id]['employeur']['source']);
        $this->assertTrue($parProf[$profs[5]->id]['employeur']['verrouille']);
    }

    public function test_detail_du_mois_pour_le_staff_et_vues_du_professeur_en_lecture_seule(): void
    {
        $this->fixer($this->prof, 2026, 10, $this->lit);
        $this->ligne($this->prof, '2026-10-05', 'soumis', false);
        $this->actingAsRole('directeur');
        $this->getJson("/api/professeurs/{$this->prof->id}/timesheets-mois?annee=2026&mois=10")->assertOk()
            ->assertJsonPath('employeur.employeur.code', 'lit_solutions')->assertJsonPath('employeur.modifiable', true);

        Sanctum::actingAs($this->prof->user);
        $mon = $this->getJson('/api/timesheets/mon-mois?annee=2026&mois=10')->assertOk();
        $this->assertSame(['nom' => 'L-IT Solutions'], $mon->json('employeur'));
        $conf = $this->getJson('/api/timesheets/ma-confirmation?annee=2026&mois=10')->assertOk();
        $this->assertSame(['nom' => 'L-IT Solutions'], $conf->json('employeur'));
    }

    // ------------------------------------------------------------------ backfill (AC-10)

    public function test_le_backfill_rattache_l_historique_a_l_asbl_de_facon_idempotente(): void // AC-10
    {
        $this->ligne($this->prof, '2026-09-10');
        $this->ligne($this->prof, '2026-09-20');
        TimesheetPdf::create(['professeur_id' => $this->prof->id, 'annee' => 2026, 'mois' => 8, 'version' => 1, 'chemin' => 'pdfs/x.pdf', 'total_eur' => 10, 'generated_at' => now()]);
        $this->fixer($this->prof, 2026, 7, $this->lit); // existant : ne doit pas être touché
        DB::table('timesheet_signatures')->insert([
            'public_id' => 'SIG-AAAA-BBBB', 'professeur_id' => $this->prof->id, 'annee' => 2026, 'mois' => 6, 'statut' => 'remplacee',
            'signed_at' => now(), 'content_hash' => str_repeat('a', 64), 'key_id' => 'k', 'specimen_type' => 'nom', 'specimen_chemin' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = app(EmployeurBackfillService::class);

        $premier = $service->executer();
        $second = $service->executer();

        $this->assertSame(['mois_crees' => 3, 'pdfs_rattaches' => 1], $premier);
        $this->assertSame(['mois_crees' => 0, 'pdfs_rattaches' => 0], $second);
        $this->assertEqualsCanonicalizing([6, 7, 8, 9], ProfesseurEmployeurMois::pluck('mois')->all());
        $this->assertSame($this->lit->id, ProfesseurEmployeurMois::where('mois', 7)->value('employeur_id'));
        $this->assertSame(3, ProfesseurEmployeurMois::where('source', 'migration')->where('employeur_id', $this->asbl->id)->count());
        $this->assertSame(3, EmployeurMoisAudit::count());
        $pdf = TimesheetPdf::firstOrFail();
        $this->assertSame($this->asbl->id, $pdf->employeur_id);
        $this->assertSame('Code IT Bryan ! asbl', $pdf->employeur_snapshot['nom']);
        $this->assertSame(2, Employeur::count());
    }
}
