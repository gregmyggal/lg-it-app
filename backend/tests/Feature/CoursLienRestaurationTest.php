<?php

namespace Tests\Feature;

use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use App\Models\Cours;
use App\Models\CoursRessource;
use App\Models\Professeur;
use App\Models\ShareCode;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Restauration, annulation de suppression, purge 6 mois, reprise des ressources et partage élève (CLS-01 T4). */
class CoursLienRestaurationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Cours $cours;

    private Professeur $alice;

    private Professeur $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->cours = Cours::factory()->create(['statut' => 'publish']);
        $classe = $this->classeAvecSessions($this->annee(), ['cours_id' => $this->cours->id]);
        $this->alice = Professeur::factory()->create();
        $this->bob = Professeur::factory()->create();
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->assigner($classe, $this->alice);
        $service->assigner($classe, $this->bob);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function creer(array $extra = []): int
    {
        return $this->postJson("/api/cours/{$this->cours->id}/liens", $extra + ['titre' => 'Doc', 'url' => 'https://example.com/doc'])
            ->assertCreated()->json('data.id');
    }

    private function derniereVersion(string $action): ClasseLienVersion
    {
        return ClasseLienVersion::where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_restaurer_une_modification_cree_une_nouvelle_version_sans_reecrire_l_historique(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer();
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'Erreur'])->assertOk();
        $v = $this->derniereVersion('modification');

        // Bob restaure (tout professeur du cours le peut)
        Sanctum::actingAs($this->bob->user);
        $this->postJson("/api/liens-versions/{$v->id}/restaurer")->assertOk()->assertJsonPath('data.titre', 'Doc')->assertJsonPath('data.version', 3);

        $r = $this->derniereVersion('restauration');
        $this->assertSame($v->id, $r->restaure_depuis_id);
        $this->assertSame($this->bob->user->id, $r->user_id);
        $this->assertSame('Erreur', $r->avant['titre']);
        $this->assertSame('Doc', $r->apres['titre']);
        $this->assertSame('Erreur', $v->fresh()->apres['titre']); // la version d'origine n'est pas modifiée
        $this->assertSame(3, ClasseLienVersion::where('classe_lien_id', $id)->count());
    }

    public function test_restaurer_une_version_quand_le_lien_a_change_depuis_demande_confirmation(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer();
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'B'])->assertOk();
        $v = $this->derniereVersion('modification');
        $this->putJson("/api/liens/{$id}", ['version' => 2, 'titre' => 'C'])->assertOk(); // quelqu'un a modifié depuis

        $this->postJson("/api/liens-versions/{$v->id}/restaurer")->assertStatus(409)->assertJsonPath('modifie_depuis.titre', 'C');
        $this->assertSame('C', ClasseLien::find($id)->titre);

        $this->postJson("/api/liens-versions/{$v->id}/restaurer", ['confirmer' => true])->assertOk()->assertJsonPath('data.titre', 'Doc');
        $this->assertSame('C', $this->derniereVersion('restauration')->avant['titre']); // la version actuelle reste dans l'historique
    }

    public function test_annuler_une_suppression_puis_defaire_cette_restauration(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer(['seance_numero' => 2]);
        $this->creer(['seance_numero' => 2]);
        $historiqueId = $this->deleteJson("/api/liens/{$id}")->assertOk()->json('historique_id');

        $this->postJson("/api/liens-versions/{$historiqueId}/restaurer")->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.ordre', 3); // remis en dernière position de sa portée
        $this->assertNull(ClasseLien::find($id)->deleted_at);
        $this->assertCount(2, $this->getJson("/api/cours/{$this->cours->id}/liens")->json('data'));
        $this->postJson("/api/liens-versions/{$historiqueId}/restaurer")->assertStatus(409); // déjà restauré

        // Défaire la restauration = ré-archiver
        $restauration = $this->derniereVersion('restauration');
        $this->postJson("/api/liens-versions/{$restauration->id}/restaurer")->assertOk();
        $this->assertSoftDeleted('classe_liens', ['id' => $id]);
    }

    public function test_une_creation_ne_se_restaure_pas(): void
    {
        Sanctum::actingAs($this->alice->user);
        $this->creer();

        $this->postJson('/api/liens-versions/'.$this->derniereVersion('creation')->id.'/restaurer')->assertStatus(422);
    }

    public function test_restaurer_un_ordre_retablit_la_liste_avant(): void
    {
        Sanctum::actingAs($this->alice->user);
        [$a, $b, $c] = [$this->creer(['titre' => 'A']), $this->creer(['titre' => 'B']), $this->creer(['titre' => 'C'])];
        $this->putJson("/api/cours/{$this->cours->id}/liens/ordre", ['seance_numero' => null, 'ids' => [$c, $a, $b]])->assertOk();
        $v = $this->derniereVersion('ordre');

        $this->postJson("/api/liens-versions/{$v->id}/restaurer")->assertOk();

        $this->assertSame(['A', 'B', 'C'], collect($this->getJson("/api/cours/{$this->cours->id}/liens")->json('data'))->pluck('titre')->all());
        $this->assertSame('restauration', ClasseLienVersion::latest('id')->first()->action);
        $this->assertSame('ordre', $v->fresh()->action); // la version d'origine reste intacte
    }

    public function test_une_version_de_plus_de_6_mois_est_refusee_410_et_purgee(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer();
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'X'])->assertOk();
        $ancienne = $this->derniereVersion('modification');
        $ancienne->forceFill(['created_at' => now()->subMonths(7)])->save();

        $this->postJson("/api/liens-versions/{$ancienne->id}/restaurer")->assertStatus(410);
        $historique = $this->getJson("/api/cours/{$this->cours->id}/liens/historique")->json('data');
        $this->assertNotContains($ancienne->id, collect($historique)->pluck('id')->all()); // plus proposée (AC-37)

        $archive = ClasseLien::create(['parent_type' => ClasseLien::PARENT_COURS, 'parent_id' => $this->cours->id, 'titre' => 'Vieux', 'url' => 'https://v.test', 'ordre' => 9]);
        $archive->delete();
        ClasseLien::withTrashed()->whereKey($archive->id)->update(['deleted_at' => now()->subMonths(7)]);

        $this->artisan('liens:purge-historique')->assertSuccessful();

        $this->assertDatabaseMissing('classe_liens_historique', ['id' => $ancienne->id]);
        $this->assertDatabaseMissing('classe_liens', ['id' => $archive->id]);
        $this->assertDatabaseHas('classe_liens', ['id' => $id]); // un lien actif est conservé
        $this->assertTrue(ClasseLienVersion::where('classe_lien_id', $id)->exists()); // et ses versions récentes
    }

    public function test_reprendre_les_anciennes_ressources_en_liens_generaux_est_idempotent(): void
    {
        CoursRessource::create(['cours_id' => $this->cours->id, 'titre_ressource' => 'Tuto', 'url_ressource' => 'https://tuto.test', 'type_ressource' => 'video', 'ordre' => 1]);
        CoursRessource::create(['cours_id' => $this->cours->id, 'titre_ressource' => 'Outil', 'url_ressource' => 'https://outil.test', 'type_ressource' => 'outil', 'ordre' => 2]);
        Sanctum::actingAs($this->alice->user);
        $this->getJson("/api/cours/{$this->cours->id}/liens")->assertJsonPath('anciennes_ressources', 2);

        $this->postJson("/api/cours/{$this->cours->id}/liens/reprendre-ressources")->assertOk()->assertJsonPath('creees', 2);
        $this->postJson("/api/cours/{$this->cours->id}/liens/reprendre-ressources")->assertOk()->assertJsonPath('creees', 0);

        $liste = collect($this->getJson("/api/cours/{$this->cours->id}/liens")->assertJsonPath('anciennes_ressources', 0)->json('data'));
        $this->assertSame(['Tuto', 'Outil'], $liste->pluck('titre')->all());
        $this->assertSame(['video', 'outil'], $liste->pluck('type')->all());
        $this->assertSame([null, null], $liste->pluck('seance_numero')->all());
        $this->assertSame(2, ClasseLienVersion::where('action', 'creation')->count());
    }

    public function test_partage_eleve_liens_generaux_et_par_seance_sans_auteur_ni_archive_ac38(): void
    {
        Sanctum::actingAs($this->alice->user);
        $this->creer(['titre' => 'Général']);
        $this->creer(['titre' => 'Séance 2', 'seance_numero' => 2]);
        $archive = $this->creer(['titre' => 'Archivé', 'seance_numero' => 2]);
        $this->deleteJson("/api/liens/{$archive}")->assertOk();
        ClasseLien::create(['parent_type' => ClasseLien::PARENT_COURS, 'parent_id' => $this->cours->id, 'titre' => 'Hors programme', 'url' => 'https://hp.test', 'seance_numero' => 15, 'ordre' => 1]);
        CoursRessource::create(['cours_id' => $this->cours->id, 'titre_ressource' => 'Ancienne', 'url_ressource' => 'https://a.test', 'type_ressource' => 'outil', 'ordre' => 1]);
        $code = ShareCode::forceCreate(['code' => ShareCode::generateCode(), 'shareable_type' => 'cours', 'shareable_id' => $this->cours->id]);

        $reponse = $this->withHeaders(['X-Share-Code' => $code->code])->getJson("/api/share/{$code->code}")->assertOk();

        $liens = $reponse->json('liens');
        $this->assertSame(['Général'], collect($liens['generaux'])->pluck('titre')->all());
        $this->assertSame([2], collect($liens['par_seance'])->pluck('seance_numero')->all());
        $this->assertSame(['Séance 2'], collect($liens['par_seance'][0]['liens'])->pluck('titre')->all());
        $this->assertStringNotContainsString('modifie_par', json_encode($liens));
        $this->assertStringNotContainsString('version', json_encode($liens));
        $this->assertStringNotContainsString('Archivé', json_encode($liens));
        $this->assertStringNotContainsString('Hors programme', json_encode($liens));
        $this->assertSame(['Ancienne'], collect($reponse->json('ressources'))->pluck('titre_ressource')->all()); // transitoire
    }
}
