<?php

namespace Tests\Feature;

use App\Mail\DepotLienMail;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\DepotEnvoi;
use App\Models\DepotFichier;
use App\Models\DepotPartage;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function test_envoi_du_lien_par_email_trace_une_adresse_masquee(): void
    {
        Mail::fake();
        [$data, $token] = $this->partager('lea');

        $rep = $this->postJson($this->url("/partages/{$data['id']}/envoyer"), ['email' => 'marie.dupont@example.be'])
            ->assertOk()->assertJsonPath('data.nb_envois', 1)->assertJsonPath('data.envois.0.destinataire', 'm***@example.be');
        $this->assertStringNotContainsString('marie', $rep->getContent());

        Mail::assertSent(DepotLienMail::class, fn (DepotLienMail $m) => $m->hasTo('marie.dupont@example.be') && str_ends_with($m->lien, $token));
        $mail = Mail::sent(DepotLienMail::class)->first();
        $this->assertStringNotContainsString('Léa', $mail->objet);
        $html = view('mail.depot.lien', get_object_vars($mail))->render();
        $this->assertStringContainsString($token, $html);
        $this->assertStringContainsString('Voir et télécharger les fichiers', $html);

        // « Envoyer à un autre parent » : même lien, une trace de plus.
        $this->postJson($this->url("/partages/{$data['id']}/envoyer"), ['email' => 'papa@example.be'])->assertOk()->assertJsonPath('data.nb_envois', 2);
        $this->assertSame(['m***@example.be', 'p***@example.be'], DepotEnvoi::orderBy('id')->pluck('destinataire_masque')->all());

        // L'adresse en clair n'est stockée nulle part dans la base de dépôt.
        $this->assertStringNotContainsString('marie', json_encode(DB::table('depot_envois')->get()));
        $this->getJson($this->url('/partages'))->assertOk()->assertJsonPath('data.0.nb_envois', 2)->assertJsonCount(2, 'data.0.envois');
    }

    public function test_envoi_refuse_si_lien_revoque_adresse_invalide_ou_droits_manquants(): void
    {
        Mail::fake();
        [$data] = $this->partager('lea');
        $id = $data['id'];

        $this->postJson($this->url("/partages/$id/envoyer"), ['email' => 'pas-une-adresse'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson($this->url("/partages/$id/envoyer"), [])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson($this->url("/partages/$id/revoquer"))->assertOk();
        $this->postJson($this->url("/partages/$id/envoyer"), ['email' => 'a@example.be'])->assertStatus(422);

        $this->actingAsRole('professeur');
        $this->postJson($this->url("/partages/$id/envoyer"), ['email' => 'a@example.be'])->assertForbidden();

        Mail::assertNothingSent();
        $this->assertSame(0, DepotEnvoi::count());
    }

    public function test_echec_du_serveur_de_mail_ne_laisse_ni_trace_ni_adresse_dans_les_logs(): void
    {
        [$data] = $this->partager('lea');
        Log::spy();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('connexion refusée pour secret.parent@example.be'));

        $this->postJson($this->url("/partages/{$data['id']}/envoyer"), ['email' => 'secret.parent@example.be'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'n\'a pas pu être envoyé') && ! str_contains($m, 'secret'));

        $this->assertSame(0, DepotEnvoi::count());
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $contexte) => ! str_contains(json_encode($contexte), 'secret'))->once();
    }

    public function test_purge_60_jours_apres_expiration_et_effacement_des_tokens_echus(): void
    {
        [$partageB] = $this->partager('lea'); // fichier B, lien qui expirera il y a 61 jours
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], 'libelle' => 'A'])->assertCreated();
        $this->postJson($this->url('/fichiers'), ['fichiers' => [UploadedFile::fake()->create('d.pdf', 10, 'application/pdf')], 'libelle' => 'D'])->assertCreated();
        $a = DepotFichier::where('libelle', 'A')->firstOrFail(); // jamais partagé, déposé il y a 61 jours
        $d = DepotFichier::where('libelle', 'D')->firstOrFail(); // jamais partagé, récent
        $b = $this->fichiers['lea'];
        $c = $this->fichiers['tom']; // dans un lien encore actif

        [$partageC] = $this->partager('tom');
        [$partageD] = $this->partager('lea', 'tom'); // lien récemment expiré (5 jours) : protège lea et tom ? non, voir ci-dessous
        DepotPartage::whereKey($partageD['id'])->delete(); // on retire ce lien : il ne sert qu'à vérifier l'unicité des tokens

        DepotPartage::whereKey($partageB['id'])->update(['expire_le' => now()->subDays(61)]);
        DepotFichier::whereIn('id', [$a->id, $b->id, $c->id])->update(['created_at' => now()->subDays(100)]);
        DepotFichier::whereKey($a->id)->update(['created_at' => now()->subDays(61)]);

        // Un lien expiré depuis 5 jours : son token chiffré doit disparaître, mais le lien et son fichier restent.
        [$partageRecent] = $this->partager('lea');
        DepotPartage::whereKey($partageRecent['id'])->update(['expire_le' => now()->subDays(5)]);

        $this->artisan('depots:purger', ['--simulation' => true])->assertSuccessful();
        $this->assertSame(4, DepotFichier::count()); // simulation : rien de supprimé

        $this->artisan('depots:purger')->assertSuccessful();

        // A (jamais partagé, 61 j) est supprimé ; B (lien expiré il y a 61 j) est protégé par le lien récent (expiré il y a 5 j).
        Storage::disk('local')->assertMissing($a->chemin);
        Storage::disk('local')->assertExists($b->chemin);
        Storage::disk('local')->assertExists($c->chemin);
        Storage::disk('local')->assertExists($d->chemin);
        $this->assertSame(3, DepotFichier::count());
        $this->assertNull(DepotPartage::findOrFail($partageRecent['id'])->token_chiffre);
        $this->assertNotNull(DepotPartage::findOrFail($partageC['id'])->token_chiffre); // lien actif : intact
        $this->assertSame('actif', DepotPartage::findOrFail($partageC['id'])->statut());
    }

    public function test_purge_supprime_fichier_et_lien_vide_quand_le_dernier_lien_a_61_jours(): void
    {
        [$partage] = $this->partager('lea');
        DepotPartage::whereKey($partage['id'])->update(['expire_le' => now()->subDays(61)]);
        DepotFichier::whereKey($this->fichiers['lea']->id)->update(['created_at' => now()->subDays(100)]);

        $this->artisan('depots:purger')->assertSuccessful();

        Storage::disk('local')->assertMissing($this->fichiers['lea']->chemin);
        $this->assertSame(0, DepotPartage::count()); // lien vidé, échu depuis plus de 60 jours : supprimé avec son journal
        $this->assertSame(1, DepotFichier::count()); // tom, récent et jamais partagé
    }
}
