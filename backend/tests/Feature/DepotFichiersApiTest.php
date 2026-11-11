<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\DepotFichier;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** DEP-01 T1 : dépôt de réalisations d'enfants sur un stage (stage entier ou séance), liste, téléchargement, suppression. */
class DepotFichiersApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private Classe $stage;

    private \App\Models\AnneeScolaire $anneeScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        Storage::fake('local');

        $this->anneeScolaire = $this->annee();
        $this->actingAsRole('admin');
        $this->stage = Classe::findOrFail($this->creerStage('2026-10-12', 'Salle B'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Stage de 5 matinées à partir du lundi donné (aucun férié ni congé du 12 au 23 octobre 2026). */
    private function creerStage(string $lundi, string $lieu): int
    {
        $jours = collect(range(0, 4))->map(fn ($i) => ['date' => Carbon::parse($lundi)->addDays($i)->toDateString(), 'demi_journee' => 'matin'])->all();

        return $this->postJson('/api/classes/stage', [
            'date_debut' => $lundi, 'stage_id' => \App\Models\Stage::firstOrCreate(['slug' => 'scratch'], ['titre' => 'Scratch', 'theme_stage' => 'Scratch', 'tranche_age' => '8-12 ans', 'lieu' => 'Namur', 'prix' => 150, 'statut' => 'publish'])->id, 'lieu' => $lieu, 'jours' => $jours,
        ])->assertCreated()->json('data.id');
    }

    private function url(string $suffixe = '', ?Classe $classe = null): string
    {
        return '/api/classes/'.($classe ?? $this->stage)->id.'/depot'.$suffixe;
    }

    private function professeurDuStage(): Professeur
    {
        $prof = Professeur::factory()->create();
        app(ClasseProfesseurAssignmentService::class)->assigner($this->stage, $prof, ['role' => 'principal']);

        return $prof;
    }

    public function test_401_et_403_professeur_non_assigne(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url())->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson($this->url())->assertStatus(403);
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertStatus(403);
    }

    public function test_une_classe_ordinaire_n_a_pas_de_depot(): void
    {
        $classe = $this->classeAvecSessions($this->anneeScolaire);

        $this->getJson($this->url('', $classe))->assertNotFound();
    }

    public function test_direction_depose_un_lot_sur_le_stage_entier_avec_libelle(): void
    {
        $reponse = $this->postJson($this->url('/fichiers'), [
            'fichiers' => [
                UploadedFile::fake()->create('jeu.sb3', 50, 'application/zip'),
                UploadedFile::fake()->create('dessin.png', 20, 'image/png'),
            ],
            'libelle' => ' Léa ',
        ])->assertCreated()->assertJsonCount(2, 'data')->assertJsonCount(0, 'refuses');

        $this->assertSame('stage', $reponse->json('data.0.portee'));
        $this->assertSame('Léa', $reponse->json('data.0.libelle'));
        $fichier = DepotFichier::firstOrFail();
        Storage::disk('local')->assertExists($fichier->chemin);
        $this->assertStringStartsWith('depots/'.$this->stage->id.'/', $fichier->chemin);
        $this->assertStringNotContainsString('jeu', $fichier->chemin); // nom serveur aléatoire

        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonCount(5, 'meta.seances')->assertJsonPath('meta.taille_max_octets', config('depot.taille_max_octets'));
    }

    public function test_professeur_du_stage_depose_sur_une_seance(): void
    {
        $prof = $this->professeurDuStage();
        Sanctum::actingAs($prof->user);
        $session = $this->stage->sessions()->orderBy('seance_numero')->skip(1)->first();

        $reponse = $this->postJson($this->url('/fichiers'), [
            'fichiers' => [UploadedFile::fake()->create('projet.pdf', 10, 'application/pdf')],
            'course_session_id' => $session->id,
        ])->assertCreated();

        $this->assertSame('seance', $reponse->json('data.0.portee'));
        $this->assertSame($session->seance_numero, $reponse->json('data.0.seance_numero'));
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_seance_d_un_autre_stage_refusee(): void
    {
        $autre = $this->classeAvecSessions($this->anneeScolaire);

        $this->postJson($this->url('/fichiers'), [
            'fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
            'course_session_id' => $autre->sessions()->first()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('course_session_id');
    }

    public function test_les_fichiers_refuses_n_empechent_pas_les_autres(): void
    {
        $reponse = $this->postJson($this->url('/fichiers'), [
            'fichiers' => [
                UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('page.html', 10, 'text/html'),
                UploadedFile::fake()->create('faux.png', 10, 'application/x-msdownload'),
                UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml'),
            ],
        ])->assertCreated()->assertJsonCount(1, 'data')->assertJsonCount(3, 'refuses');

        $this->assertSame(['page.html', 'faux.png', 'image.svg'], array_column($reponse->json('refuses'), 'nom'));
        $this->assertStringContainsString('non accepté', $reponse->json('refuses.0.message'));
        $this->assertStringContainsString('ne correspond pas', $reponse->json('refuses.1.message'));
        $this->assertSame(1, DepotFichier::count());
    }

    public function test_tout_refuse_renvoie_422(): void
    {
        $this->postJson($this->url('/fichiers'), [
            'fichiers' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])->assertStatus(422)->assertJsonCount(1, 'refuses');
        $this->assertSame(0, DepotFichier::count());
    }

    public function test_taille_maximale_et_quota_par_espace(): void
    {
        config(['depot.taille_max_octets' => 100 * 1024, 'depot.quota_espace_octets' => 150 * 1024]);

        $reponse = $this->postJson($this->url('/fichiers'), [
            'fichiers' => [
                UploadedFile::fake()->create('gros.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('a.pdf', 90, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 90, 'application/pdf'),
            ],
        ])->assertCreated()->assertJsonCount(1, 'data')->assertJsonCount(2, 'refuses');

        $this->assertStringContainsString('trop volumineux', $reponse->json('refuses.0.message'));
        $this->assertStringContainsString('Espace plein', $reponse->json('refuses.1.message'));
    }

    public function test_telechargement_en_piece_jointe_et_suppression(): void
    {
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('projet.pdf', 10, 'application/pdf')]])->assertCreated();
        $fichier = DepotFichier::firstOrFail();

        $telechargement = $this->get($this->url('/fichiers/'.$fichier->id.'/telecharger'))->assertOk();
        $this->assertStringContainsString('attachment', $telechargement->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $telechargement->headers->get('Content-Type'));
        $this->assertSame('nosniff', $telechargement->headers->get('X-Content-Type-Options'));

        $this->deleteJson($this->url('/fichiers/'.$fichier->id))->assertNoContent();
        Storage::disk('local')->assertMissing($fichier->chemin);
        $this->assertSame(0, DepotFichier::count());
    }

    public function test_un_fichier_d_un_autre_stage_est_introuvable(): void
    {
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertCreated();
        $fichier = DepotFichier::firstOrFail();

        $autreId = $this->creerStage('2026-10-19', 'Salle C');

        $this->deleteJson('/api/classes/'.$autreId.'/depot/fichiers/'.$fichier->id)->assertNotFound();
        $this->get('/api/classes/'.$autreId.'/depot/fichiers/'.$fichier->id.'/telecharger')->assertNotFound();
    }

    public function test_professeur_non_assigne_ne_peut_ni_telecharger_ni_supprimer(): void
    {
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertCreated();
        $fichier = DepotFichier::firstOrFail();

        $this->actingAsRole('professeur');
        $this->get($this->url('/fichiers/'.$fichier->id.'/telecharger'))->assertForbidden();
        $this->deleteJson($this->url('/fichiers/'.$fichier->id))->assertForbidden();
        $this->assertSame(1, DepotFichier::count());
    }

    public function test_supprimer_le_stage_supprime_ses_fichiers_du_disque(): void
    {
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertCreated();
        $chemin = DepotFichier::firstOrFail()->chemin;

        $this->deleteJson('/api/classes/'.$this->stage->id)->assertNoContent();

        Storage::disk('local')->assertMissing($chemin);
        $this->assertSame(0, DepotFichier::count());
    }

    public function test_fichiers_roblox_studio_acceptes_seulement_avec_la_bonne_signature(): void
    {
        $reponse = $this->postJson($this->url('/fichiers'), [
            'fichiers' => [
                UploadedFile::fake()->createWithContent('monde.rbxl', "<roblox!\x89\xff\r\n\x1a\n\x00\x00donnees"),
                UploadedFile::fake()->createWithContent('modele.rbxm', "<roblox!\x89\xff\r\n\x1a\n\x00\x00donnees"),
                UploadedFile::fake()->createWithContent('monde.rbxlx', '<roblox xmlns:xmime="http://www.w3.org/2005/05/xmlmime" version="4"><Item class="Workspace"/></roblox>'),
                UploadedFile::fake()->createWithContent('modele.rbxmx', '<roblox version="4"></roblox>'),
                // Mauvais contenu sous une extension Roblox : refusés.
                UploadedFile::fake()->createWithContent('faux.rbxl', '<html><script>alert(1)</script></html>'),
                UploadedFile::fake()->createWithContent('faux.rbxlx', '<html>pas roblox</html>'),
                UploadedFile::fake()->create('vide.rbxm', 10, 'application/octet-stream'),
            ],
        ])->assertCreated()->assertJsonCount(4, 'data')->assertJsonCount(3, 'refuses');

        $this->assertSame(['faux.rbxl', 'faux.rbxlx', 'vide.rbxm'], array_column($reponse->json('refuses'), 'nom'));
        $this->assertSame(4, DepotFichier::count());
        $this->getJson($this->url())->assertOk()->assertJsonPath('meta.extensions', fn ($ext) => array_diff(['rbxl', 'rbxm', 'rbxlx', 'rbxmx'], $ext) === []);
    }
}
