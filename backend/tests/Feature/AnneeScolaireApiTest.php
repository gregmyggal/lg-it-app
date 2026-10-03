<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class AnneeScolaireApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'libelle' => '2027-2028',
            'date_debut' => '2027-08-23',
            'date_fin' => '2028-07-03',
            'periodes' => [
                ['numero' => 1, 'date_debut' => '2027-08-23', 'date_fin' => '2028-02-18'],
                ['numero' => 2, 'date_debut' => '2028-02-21', 'date_fin' => '2028-07-03'],
            ],
        ];
    }

    public function test_401_sans_authentification(): void
    {
        $this->getJson('/api/annees-scolaires')->assertStatus(401)->assertJson(['message' => 'Non authentifié.']);
        $this->postJson('/api/annees-scolaires', $this->payload())->assertStatus(401);
    }

    public function test_403_pour_un_professeur(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('professeur');

        $this->getJson('/api/annees-scolaires')->assertStatus(403)->assertJson(['message' => 'Action non autorisée.']);
        $this->getJson("/api/annees-scolaires/{$annee->id}")->assertStatus(403);
        $this->postJson('/api/annees-scolaires', $this->payload())->assertStatus(403);
        $this->putJson("/api/annees-scolaires/{$annee->id}", ['statut' => 'archivee'])->assertStatus(403);
        $this->deleteJson("/api/annees-scolaires/{$annee->id}")->assertStatus(403);
    }

    public function test_creation_avec_ses_deux_periodes(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson('/api/annees-scolaires', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.libelle', '2027-2028')
            ->assertJsonPath('data.statut', 'active')
            ->assertJsonCount(2, 'data.periodes')
            ->assertJsonPath('data.periodes.1.numero', 2)
            ->assertJsonPath('data.can.update', true);

        $this->assertDatabaseCount('periodes', 2);
    }

    public function test_422_validation(): void
    {
        $this->actingAsRole('admin');
        $this->annee();

        $this->postJson('/api/annees-scolaires', [])->assertStatus(422)->assertJsonValidationErrors(['libelle', 'date_debut', 'date_fin', 'periodes']);

        // libellé déjà utilisé, message en français
        $this->postJson('/api/annees-scolaires', $this->payload(['libelle' => '2026-2027']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['libelle'])
            ->assertJsonPath('errors.libelle.0', 'La valeur de libellé est déjà utilisée.');

        // période 2 qui chevauche la période 1 (RG-3)
        $chevauche = $this->payload(['periodes' => [
            ['numero' => 1, 'date_debut' => '2027-08-23', 'date_fin' => '2028-02-18'],
            ['numero' => 2, 'date_debut' => '2028-02-10', 'date_fin' => '2028-07-03'],
        ]]);
        $this->postJson('/api/annees-scolaires', $chevauche)
            ->assertStatus(422)
            ->assertJsonPath('errors.periodes.0', 'La période 2 doit commencer après la fin de la période 1 (18/02/2028). Au plus tôt le 19/02/2028.');

        // période hors de l'année
        $hors = $this->payload(['periodes' => [
            ['numero' => 1, 'date_debut' => '2027-08-01', 'date_fin' => '2028-02-18'],
            ['numero' => 2, 'date_debut' => '2028-02-21', 'date_fin' => '2028-07-03'],
        ]]);
        $this->postJson('/api/annees-scolaires', $hors)->assertStatus(422)->assertJsonValidationErrors(['periodes']);

        // une seule période
        $this->postJson('/api/annees-scolaires', $this->payload(['periodes' => [['numero' => 1, 'date_debut' => '2027-08-23', 'date_fin' => '2028-02-18']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['periodes']);

        $this->assertSame(1, AnneeScolaire::count());
    }

    public function test_liste_et_detail_incluent_les_periodes(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('directeur');

        $this->getJson('/api/annees-scolaires')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.periodes');
        $this->getJson("/api/annees-scolaires/{$annee->id}")->assertOk()->assertJsonPath('data.libelle', '2026-2027');
        $this->getJson('/api/annees-scolaires/9999')->assertStatus(404);
    }

    public function test_mise_a_jour_statut_et_periode(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('admin');

        $this->putJson("/api/annees-scolaires/{$annee->id}", [
            'statut' => 'brouillon',
            'version' => $annee->updated_at->toIso8601String(),
            'periodes' => [
                ['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-12'],
                ['numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02'],
            ],
        ])->assertOk()->assertJsonPath('data.statut', 'brouillon')->assertJsonPath('data.periodes.0.date_fin', '2027-02-12');

        $version = $annee->fresh()->updated_at->toIso8601String();
        $this->putJson("/api/annees-scolaires/{$annee->id}", ['date_fin' => '2026-01-01', 'version' => $version])->assertStatus(422)->assertJsonValidationErrors(['date_fin']);
        $this->putJson("/api/annees-scolaires/{$annee->id}", ['statut' => 'inconnu'])->assertStatus(422);
    }

    public function test_raccourcir_une_periode_avec_des_sessions_nest_plus_bloque(): void
    {
        $annee = $this->annee();
        $classe = $this->classeAvecSessions($annee); // dernière séance le 2027-01-06
        $this->actingAsRole('admin');

        $this->putJson("/api/annees-scolaires/{$annee->id}", ['version' => $annee->updated_at->toIso8601String(), 'periodes' => [
            ['numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2026-12-18'],
            ['numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02'],
        ]])->assertOk();

        $this->assertSame('2026-12-18', $annee->periodes()->where('numero', 1)->first()->date_fin->toDateString());
        $this->getJson("/api/classes/{$classe->id}")->assertJsonPath('data.periodes.0.nb_hors_periode', 3);
    }

    public function test_suppression_409_avec_classes_puis_204_sans(): void
    {
        $annee = $this->annee();
        $this->classeAvecSessions($annee);
        $this->actingAsRole('admin');

        $this->deleteJson("/api/annees-scolaires/{$annee->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'annee_non_supprimable')
            ->assertJsonPath('classes_count', 1)
            ->assertJsonPath('sessions_count', 14)
            ->assertJsonPath('message', 'Contient 1 classe (14 séances) : archivez-la plutôt.');

        $vide = AnneeScolaire::factory()->avecPeriodes()->create();
        $this->deleteJson("/api/annees-scolaires/{$vide->id}")->assertStatus(204);
        $this->assertDatabaseMissing('annees_scolaires', ['id' => $vide->id]);
        $this->assertDatabaseMissing('periodes', ['annee_scolaire_id' => $vide->id]);
    }
}
