<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use App\Models\Cours;
use App\Models\Professeur;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Liens d'un cours (CLS-01 T4) : droits, liste, création/modification/archivage versionnés, ordre, conflit. */
class CoursLienApiTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private Cours $cours;

    private Professeur $alice;

    private Professeur $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->cours = Cours::factory()->create(['statut' => 'publish']);
        $classe = $this->classeAvecSessions($this->annee(), ['cours_id' => $this->cours->id]);
        $this->alice = Professeur::factory()->create();
        $this->carol = Professeur::factory()->create(); // aucune classe de ce cours
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $this->alice);
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

    public function test_401_sans_authentification(): void
    {
        $this->getJson("/api/cours/{$this->cours->id}/liens")->assertUnauthorized();
    }

    public function test_lecture_pour_tout_professeur_ecriture_pour_assignation_active_ou_staff(): void
    {
        Sanctum::actingAs($this->carol->user);
        $r = $this->getJson("/api/cours/{$this->cours->id}/liens")->assertOk();
        $this->assertSame($this->cours->titre, $r->json('cours.titre'));
        $this->assertCount(1, $r->json('classes')); // classes actives du cours (bandeau de portée)
        $this->assertSame(1, $r->json('classes.0.seance_courante.seance_numero'));
        $this->getJson("/api/cours/{$this->cours->id}/liens")->assertOk()->assertJsonPath('peut_modifier', false)->assertJsonPath('peut_voir_historique', false);
        $this->postJson("/api/cours/{$this->cours->id}/liens", ['titre' => 'X', 'url' => 'https://x.test'])->assertForbidden();
        $this->getJson("/api/cours/{$this->cours->id}/liens/historique")->assertForbidden();

        Sanctum::actingAs($this->alice->user);
        $this->getJson("/api/cours/{$this->cours->id}/liens")->assertOk()->assertJsonPath('peut_modifier', true)->assertJsonPath('peut_voir_historique', true);
        $this->creer();

        $this->actingAsRole('directeur');
        $this->creer(['titre' => 'Staff']);
    }

    public function test_liste_generaux_puis_seances_archives_exclus_et_hors_programme(): void
    {
        Sanctum::actingAs($this->alice->user);
        $this->creer(['titre' => 'Séance 3', 'seance_numero' => 3]);
        $this->creer(['titre' => 'Général A']);
        $a = $this->creer(['titre' => 'Archivé']);
        $this->creer(['titre' => 'Séance 1', 'seance_numero' => 1, 'type' => 'video']);
        ClasseLien::create(['parent_type' => ClasseLien::PARENT_COURS, 'parent_id' => $this->cours->id, 'titre' => 'HP', 'url' => 'https://hp.test', 'seance_numero' => 15, 'ordre' => 1]);
        $this->deleteJson("/api/liens/{$a}")->assertOk()->assertJsonStructure(['historique_id']);

        $liste = collect($this->getJson("/api/cours/{$this->cours->id}/liens")->assertOk()->json('data'));

        $this->assertSame(['Général A', 'Séance 1', 'Séance 3', 'HP'], $liste->pluck('titre')->all());
        $this->assertSame([null, 1, 3, 15], $liste->pluck('seance_numero')->all());
        $this->assertTrue($liste->last()['hors_programme']);
        $this->assertSame('video', $liste[1]['type']);
        $this->assertArrayNotHasKey('pinned', $liste[0]);
        $this->assertArrayNotHasKey('actif', $liste[0]);
    }

    public function test_creation_validation_ordre_et_version(): void
    {
        Sanctum::actingAs($this->alice->user);

        $this->postJson("/api/cours/{$this->cours->id}/liens", ['titre' => 'X', 'url' => 'ftp://x.test'])->assertStatus(422)->assertJsonValidationErrors('url');
        $this->postJson("/api/cours/{$this->cours->id}/liens", ['titre' => 'X', 'url' => 'https://x.test', 'seance_numero' => 15])->assertStatus(422)->assertJsonValidationErrors('seance_numero');
        $this->postJson("/api/cours/{$this->cours->id}/liens", ['titre' => 'X', 'url' => 'https://x.test', 'type' => 'musique'])->assertStatus(422);

        $id1 = $this->creer(['seance_numero' => 2]);
        $id2 = $this->creer(['seance_numero' => 2]);

        $this->assertSame(1, ClasseLien::find($id1)->ordre);
        $this->assertSame(2, ClasseLien::find($id2)->ordre); // dernier de sa portée
        $this->assertSame(1, ClasseLien::find($id1)->version);
        $v = ClasseLienVersion::where('classe_lien_id', $id1)->sole();
        $this->assertSame('creation', $v->action);
        $this->assertNull($v->avant);
        $this->assertSame('Doc', $v->apres['titre']);
        $this->assertSame($this->alice->user->id, $v->user_id);
    }

    public function test_modification_une_version_avant_apres_et_changement_de_portee(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer(['seance_numero' => 1]);
        $autre = $this->creer(['seance_numero' => 4]);

        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'Nouveau titre'])
            ->assertOk()->assertJsonPath('data.titre', 'Nouveau titre')->assertJsonPath('data.version', 2);
        $v = ClasseLienVersion::where('classe_lien_id', $id)->where('action', 'modification')->sole();
        $this->assertSame('Doc', $v->avant['titre']);
        $this->assertSame('Nouveau titre', $v->apres['titre']);

        $this->putJson("/api/liens/{$id}", ['version' => 2, 'seance_numero' => 4])->assertOk()->assertJsonPath('data.seance_numero', 4)->assertJsonPath('data.ordre', 2);
        $this->assertSame(1, ClasseLienVersion::where('classe_lien_id', $id)->where('action', 'portee')->count());
        $this->assertSame(1, ClasseLien::find($autre)->ordre);

        // Aucun changement : pas de nouvelle version.
        $this->putJson("/api/liens/{$id}", ['version' => 3, 'titre' => 'Nouveau titre'])->assertOk();
        $this->assertSame(3, ClasseLienVersion::where('classe_lien_id', $id)->count());
    }

    public function test_conflit_d_edition_409_sans_ecrasement(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer();
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'Version d\'Alice'])->assertOk();

        $this->actingAsRole('directeur');
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'Version obsolète'])
            ->assertStatus(409)->assertJsonPath('lien.titre', 'Version d\'Alice')->assertJsonPath('lien.version', 2);

        $this->assertSame('Version d\'Alice', ClasseLien::find($id)->titre);
        $this->putJson("/api/liens/{$id}", ['titre' => 'Sans version'])->assertStatus(422)->assertJsonValidationErrors('version');
    }

    public function test_archivage_ne_supprime_pas_physiquement_et_cree_une_version(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer();

        $r = $this->deleteJson("/api/liens/{$id}")->assertOk();

        $this->assertSoftDeleted('classe_liens', ['id' => $id]);
        $v = ClasseLienVersion::find($r->json('historique_id'));
        $this->assertSame('archivage', $v->action);
        $this->assertNull($v->apres);
        $this->assertSame('Doc', $v->avant['titre']);
        $this->getJson("/api/cours/{$this->cours->id}/liens")->assertJsonCount(0, 'data');

        // Un lien déjà archivé n'est plus adressable (404) ; sans droit d'écriture, l'archivage d'un lien actif est refusé.
        $this->deleteJson("/api/liens/{$id}")->assertNotFound();
        $actif = $this->creer(['titre' => 'Actif']);
        Sanctum::actingAs($this->carol->user);
        $this->deleteJson("/api/liens/{$actif}")->assertForbidden();
    }

    public function test_ordre_un_lot_de_deplacements_est_une_seule_version(): void
    {
        Sanctum::actingAs($this->alice->user);
        [$a, $b, $c] = [$this->creer(['titre' => 'A']), $this->creer(['titre' => 'B']), $this->creer(['titre' => 'C'])];

        $this->putJson("/api/cours/{$this->cours->id}/liens/ordre", ['seance_numero' => null, 'ids' => [$b, $a, $c]])->assertOk();
        $this->putJson("/api/cours/{$this->cours->id}/liens/ordre", ['seance_numero' => null, 'ids' => [$c, $b, $a]])->assertOk();

        $this->assertSame(['C', 'B', 'A'], collect($this->getJson("/api/cours/{$this->cours->id}/liens")->json('data'))->pluck('titre')->all());
        $ordre = ClasseLienVersion::where('action', 'ordre')->get();
        $this->assertCount(1, $ordre); // lot : même auteur, même portée, < 5 min
        $this->assertSame(['A', 'B', 'C'], collect($ordre[0]->avant['liste'])->pluck('titre')->all());
        $this->assertSame(['C', 'B', 'A'], collect($ordre[0]->apres['liste'])->pluck('titre')->all());

        $this->putJson("/api/cours/{$this->cours->id}/liens/ordre", ['seance_numero' => null, 'ids' => [$a, $b]])->assertStatus(422);
    }

    public function test_historique_filtres_et_droits_selon_l_assignation(): void
    {
        Sanctum::actingAs($this->alice->user);
        $id = $this->creer(['seance_numero' => 2]);
        $this->creer(['titre' => 'Général']);
        $this->putJson("/api/liens/{$id}", ['version' => 1, 'titre' => 'Modifié'])->assertOk();

        $tous = $this->getJson("/api/cours/{$this->cours->id}/liens/historique")->assertOk()->json('data');
        $this->assertCount(3, $tous);
        $this->assertCount(2, $this->getJson("/api/cours/{$this->cours->id}/liens/historique?portee=2")->json('data'));
        $this->assertCount(1, $this->getJson("/api/cours/{$this->cours->id}/liens/historique?portee=generaux")->json('data'));
        $this->assertCount(1, $this->getJson("/api/cours/{$this->cours->id}/liens/historique?action=modification")->json('data'));
        $this->assertNotNull($tous[0]['auteur']);

        // Assignation terminée : historique consultable, restauration refusée.
        $service = app(ClasseProfesseurAssignmentService::class);
        $service->terminer($service->assignation(Classe::first(), $this->alice), '2026-09-30');
        Carbon::setTestNow('2026-10-02 10:00:00');
        Sanctum::actingAs($this->alice->user);
        $h = $this->getJson("/api/cours/{$this->cours->id}/liens/historique")->assertOk()->json('data');
        $this->assertFalse($h[0]['peut_restaurer']);
        $this->postJson("/api/liens-versions/{$h[0]['id']}/restaurer")->assertForbidden();
    }
}
