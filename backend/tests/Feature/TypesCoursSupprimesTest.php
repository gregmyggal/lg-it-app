<?php

namespace Tests\Feature;

use App\Models\Cours;
use App\Models\Formation;
use App\Models\Professeur;
use App\Models\ShareCode;
use App\Models\TypeFormation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** CLS-01 T5 : la notion de type de cours a disparu (API, réponses, partage) ; les types de formation restent. */
class TypesCoursSupprimesTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    public function test_les_anciennes_routes_types_cours_repondent_404(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/api/types-cours')->assertNotFound();
        $this->postJson('/api/types-cours', ['nom' => 'X', 'slug' => 'x'])->assertNotFound();
        $this->getJson('/api/types-cours/1')->assertNotFound();
        $this->putJson('/api/types-cours/1', ['nom' => 'Y'])->assertNotFound();
        $this->deleteJson('/api/types-cours/1')->assertNotFound();
    }

    public function test_un_ancien_client_qui_envoie_types_cours_n_est_pas_en_erreur_et_la_cle_disparait(): void
    {
        $this->actingAsRole('admin');

        $cours = $this->postJson('/api/cours', ['titre' => 'React', 'slug' => 'react-t5', 'types_cours' => [1, 2]])->assertCreated();
        $this->assertArrayNotHasKey('types_cours', $cours->json());
        $this->putJson("/api/cours/{$cours->json('id')}", ['titre' => 'React 2', 'types_cours' => [3]])->assertOk()->assertJsonMissingPath('types_cours');
        $this->getJson("/api/cours/{$cours->json('id')}")->assertOk()->assertJsonMissingPath('types_cours');
        $this->assertArrayNotHasKey('types_cours', $this->getJson('/api/cours')->assertOk()->json('0'));

        $prof = $this->postJson('/api/professeurs', [
            'prenom' => 'Zoé', 'nom' => 'Test', 'email' => 'zoe@test.be', 'login_email' => 'zoe.login@test.be',
            'date_entree' => '2026-01-01', 'types_cours' => [1],
        ])->assertCreated();
        $this->assertArrayNotHasKey('types_cours', $prof->json('data'));
        $this->putJson("/api/professeurs/{$prof->json('data.id')}", ['telephone' => '0470', 'types_cours' => [2]])->assertOk()->assertJsonMissingPath('types_cours');
        $this->getJson("/api/professeurs/{$prof->json('data.id')}")->assertOk()->assertJsonMissingPath('types_cours');
        $this->assertArrayNotHasKey('types_cours', $this->getJson('/api/professeurs')->assertOk()->json('0'));
    }

    public function test_me_ne_charge_plus_les_types_du_professeur(): void
    {
        $prof = Professeur::factory()->create();
        Sanctum::actingAs($prof->user);

        $this->getJson('/api/me')->assertOk()->assertJsonPath('professeur.id', $prof->id)->assertJsonMissingPath('professeur.types_cours');
    }

    public function test_le_partage_d_un_cours_n_expose_plus_de_types(): void
    {
        $cours = Cours::factory()->create(['statut' => 'publish']);
        $code = ShareCode::forceCreate(['code' => ShareCode::generateCode(), 'shareable_type' => 'cours', 'shareable_id' => $cours->id]);

        $reponse = $this->withHeaders(['X-Share-Code' => $code->code])->getJson("/api/share/{$code->code}")->assertOk();

        $this->assertArrayNotHasKey('types', $reponse->json());
    }

    public function test_le_partage_d_une_formation_conserve_ses_types_de_formation(): void
    {
        $formation = Formation::forceCreate(['titre' => 'Formation test', 'slug' => 'formation-test', 'statut' => 'publish']);
        $formation->typesFormation()->attach(TypeFormation::create(['nom' => 'Adultes', 'slug' => 'adultes'])->id);
        $code = ShareCode::forceCreate(['code' => ShareCode::generateCode(), 'shareable_type' => 'formation', 'shareable_id' => $formation->id]);

        $reponse = $this->withHeaders(['X-Share-Code' => $code->code])->getJson("/api/share/{$code->code}")->assertOk();

        $this->assertSame(['Adultes'], collect($reponse->json('types'))->pluck('nom')->all());
        $this->actingAsRole('directeur');
        $this->getJson('/api/types-formation')->assertOk()->assertJsonCount(1);
    }
}
