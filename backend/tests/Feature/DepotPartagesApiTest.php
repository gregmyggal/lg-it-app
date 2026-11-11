<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Cours;
use App\Models\DepotFichier;
use App\Models\DepotPartage;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** DEP-01 T2 : liens parents = sélection de fichiers, 7 jours, régénérables, révocables ; page parent publique. */
class DepotPartagesApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private Classe $stage;

    /** @var array<string, DepotFichier> */
    private array $fichiers = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        Storage::fake('local');
        $this->annee();
        $this->actingAsRole('admin');
        $this->stage = Classe::findOrFail($this->creerStage('2026-10-12'));

        foreach (['lea' => 'Léa', 'tom' => 'Tom'] as $cle => $prenom) {
            $this->postJson($this->url('/fichiers'), [
                'fichiers' => [UploadedFile::fake()->create("$cle.pdf", 10, 'application/pdf')], 'libelle' => $prenom,
            ])->assertCreated();
            $this->fichiers[$cle] = DepotFichier::where('libelle', $prenom)->firstOrFail();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function creerStage(string $lundi): int
    {
        $jours = collect(range(0, 4))->map(fn ($i) => ['date' => Carbon::parse($lundi)->addDays($i)->toDateString(), 'demi_journee' => 'matin'])->all();

        return $this->postJson('/api/classes/stage', [
            'date_debut' => $lundi, 'cours_id' => Cours::factory()->create()->id, 'lieu' => 'Salle B', 'jours' => $jours,
        ])->assertCreated()->json('data.id');
    }

    private function url(string $suffixe = ''): string
    {
        return '/api/classes/'.$this->stage->id.'/depot'.$suffixe;
    }

    /** @return array{0: array, 1: string} [données du partage, token en clair] */
    private function partager(string ...$cles): array
    {
        $rep = $this->postJson($this->url('/partages'), ['fichier_ids' => array_map(fn ($c) => $this->fichiers[$c]->id, $cles)])->assertCreated();
        $this->assertMatchesRegularExpression('#/depot/([a-f0-9]{64})$#', $rep->json('lien'));
        preg_match('#/depot/([a-f0-9]{64})$#', $rep->json('lien'), $m);

        return [$rep->json('data'), $m[1]];
    }

    public function test_401_et_403_professeur_non_assigne(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url('/partages'))->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson($this->url('/partages'))->assertForbidden();
        $this->postJson($this->url('/partages'), ['fichier_ids' => [$this->fichiers['lea']->id]])->assertForbidden();
    }

    public function test_creation_d_un_partage_par_selection_de_fichiers(): void
    {
        [$data, $token] = $this->partager('lea');

        $this->assertSame('actif', $data['statut']);
        $this->assertSame(1, $data['nb_fichiers']);
        $this->assertSame('Léa', $data['libelle']);
        $this->assertSame(now()->addDays(7)->toIso8601String(), $data['expire_le']);

        $partage = DepotPartage::firstOrFail();
        $this->assertSame(hash('sha256', $token), $partage->token_hash);
        $this->assertStringNotContainsString($token, (string) $partage->token_chiffre); // chiffré, jamais en clair
        $this->assertStringNotContainsString($token, json_encode($data)); // pas de token dans la ressource

        $this->getJson($this->url('/partages'))->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.lien');
        $this->getJson($this->url('/partages/'.$partage->id.'/lien'))->assertOk()->assertJsonPath('lien', fn ($l) => str_ends_with($l, $token));
    }

    public function test_libelle_vide_si_les_fichiers_ont_des_libelles_differents(): void
    {
        [$data] = $this->partager('lea', 'tom');

        $this->assertNull($data['libelle']);
        $this->assertSame(2, $data['nb_fichiers']);
    }

    public function test_fichier_d_un_autre_stage_ou_selection_vide_refuses(): void
    {
        $autre = Classe::findOrFail($this->creerStage('2026-10-19'));
        $this->postJson('/api/classes/'.$autre->id.'/depot/fichiers', ['fichiers' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])->assertCreated();
        $etranger = DepotFichier::where('nom_original', 'x.pdf')->firstOrFail();

        $this->postJson($this->url('/partages'), ['fichier_ids' => [$this->fichiers['lea']->id, $etranger->id]])
            ->assertStatus(422)->assertJsonValidationErrors('fichier_ids');
        $this->postJson($this->url('/partages'), ['fichier_ids' => []])->assertStatus(422)->assertJsonValidationErrors('fichier_ids');
        $this->assertSame(0, DepotPartage::count());
    }

    public function test_page_parent_montre_uniquement_la_selection_et_compte_les_ouvertures(): void
    {
        [, $token] = $this->partager('lea');
        $this->app['auth']->forgetGuards();

        $rep = $this->getJson("/api/public/depot/$token")->assertOk()->assertJsonCount(1, 'data.fichiers')
            ->assertJsonPath('data.fichiers.0.nom', 'lea.pdf')->assertJsonPath('data.libelle', 'Léa');
        $this->assertStringContainsString('noindex', $rep->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $rep->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('tom.pdf', $rep->getContent());

        $this->getJson("/api/public/depot/$token")->assertOk();
        $partage = DepotPartage::firstOrFail();
        $this->assertSame(2, $partage->nb_ouvertures);
        $this->assertNotNull($partage->derniere_ouverture_le);
    }

    public function test_telechargement_parent_limite_a_la_selection(): void
    {
        [, $token] = $this->partager('lea');
        $this->app['auth']->forgetGuards();

        $ok = $this->get("/api/public/depot/$token/fichiers/".$this->fichiers['lea']->id)->assertOk();
        $this->assertStringContainsString('attachment', $ok->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $ok->headers->get('X-Content-Type-Options'));

        $this->get("/api/public/depot/$token/fichiers/".$this->fichiers['tom']->id)->assertNotFound();
    }

    public function test_lien_inconnu_expire_ou_revoque_donnent_la_meme_reponse(): void
    {
        [$data, $token] = $this->partager('lea');
        $this->app['auth']->forgetGuards();
        $inconnu = $this->getJson('/api/public/depot/'.str_repeat('a', 64))->assertStatus(410);
        $this->getJson('/api/public/depot/pas-un-token')->assertStatus(410);

        Carbon::setTestNow('2026-10-08 10:00:01'); // 7 jours + 1 s
        $expire = $this->getJson("/api/public/depot/$token")->assertStatus(410);
        $this->get("/api/public/depot/$token/fichiers/".$this->fichiers['lea']->id)->assertStatus(410);
        $this->assertSame($inconnu->json(), $expire->json());
        $this->assertSame('expire', DepotPartage::firstOrFail()->statut()); // calculé, jamais stocké
    }

    public function test_revocation_immediate_et_regeneration(): void
    {
        [$data, $ancien] = $this->partager('lea');
        $id = $data['id'];

        $this->postJson($this->url("/partages/$id/revoquer"))->assertOk()->assertJsonPath('data.statut', 'revoque')->assertJsonMissingPath('lien');
        $this->assertNull(DepotPartage::firstOrFail()->token_chiffre);
        $this->getJson($this->url("/partages/$id/lien"))->assertStatus(410);
        $this->getJson("/api/public/depot/$ancien")->assertStatus(410);

        Carbon::setTestNow('2026-10-02 09:00:00');
        $rep = $this->postJson($this->url("/partages/$id/regenerer"))->assertOk()->assertJsonPath('data.statut', 'actif');
        preg_match('#/depot/([a-f0-9]{64})$#', $rep->json('lien'), $m);
        $this->assertNotSame($ancien, $m[1]);
        $this->assertSame(now()->addDays(7)->toIso8601String(), $rep->json('data.expire_le'));
        $this->assertSame(1, $rep->json('data.nb_fichiers'));

        $this->getJson("/api/public/depot/$ancien")->assertStatus(410);
        $this->getJson("/api/public/depot/{$m[1]}")->assertOk();
    }

    public function test_supprimer_un_fichier_le_retire_du_partage(): void
    {
        [, $token] = $this->partager('lea', 'tom');
        $this->deleteJson($this->url('/fichiers/'.$this->fichiers['tom']->id))->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/public/depot/$token")->assertOk()->assertJsonCount(1, 'data.fichiers');
    }

    public function test_un_partage_d_un_autre_stage_est_introuvable(): void
    {
        [$data] = $this->partager('lea');
        $autre = $this->creerStage('2026-10-19');

        $this->postJson("/api/classes/$autre/depot/partages/{$data['id']}/revoquer")->assertNotFound();
        $this->getJson("/api/classes/$autre/depot/partages/{$data['id']}/lien")->assertNotFound();
    }

    public function test_professeur_du_stage_peut_partager(): void
    {
        $prof = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($this->stage, $prof, ['role' => 'principal']);
        Sanctum::actingAs($prof->user);

        $this->postJson($this->url('/partages'), ['fichier_ids' => [$this->fichiers['lea']->id]])->assertCreated();
    }
}
