<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

class CalendrierScolaireApiTest extends TestCase
{
    use PrepareScolarite, RefreshDatabase;

    public function test_401_et_403(): void
    {
        $annee = $this->annee();
        $entree = CalendrierScolaire::factory()->create(['annee_scolaire_id' => $annee->id]);

        $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier")->assertStatus(401);
        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")->assertStatus(401);

        $this->actingAsRole('professeur');
        $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier")->assertStatus(403);
        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier", [])->assertStatus(403);
        $this->putJson("/api/calendrier-scolaire/{$entree->id}", ['libelle' => 'x'])->assertStatus(403);
        $this->deleteJson("/api/calendrier-scolaire/{$entree->id}")->assertStatus(403);
        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")->assertStatus(403);
    }

    public function test_creation_dune_entree_ecole_et_validation(): void
    {
        $annee = $this->annee();
        $this->actingAsRole('directeur');

        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier", [
            'date_debut' => '2026-12-14', 'date_fin' => '2026-12-14', 'type' => 'fermeture', 'libelle' => 'Fermeture école',
            'source' => 'fwb', // ignoré : une entrée créée à la main est toujours « école »
        ])->assertStatus(201)->assertJsonPath('data.source', 'ecole')->assertJsonPath('data.masque', false);

        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier", [
            'date_debut' => '2026-12-14', 'date_fin' => '2026-12-10', 'type' => 'autre',
        ])->assertStatus(422)->assertJsonValidationErrors(['date_fin', 'type', 'libelle']);
    }

    public function test_liste_filtres_et_masques(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        CalendrierScolaire::factory()->create(['annee_scolaire_id' => $annee->id, 'type' => 'fermeture', 'libelle' => 'École']);
        $armistice = CalendrierScolaire::where('cle_fwb', 'ferie-armistice-2026')->first();
        $armistice->update(['masque' => true]);
        $this->actingAsRole('directeur');

        $tous = $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier")->assertOk();
        $this->assertNotContains($armistice->id, $tous->json('data.*.id'));

        $avecMasques = $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier?avec_masques=1")->assertOk();
        $this->assertContains($armistice->id, $avecMasques->json('data.*.id'));

        $ecole = $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier?source=ecole")->assertOk();
        $this->assertSame(['ecole'], array_unique($ecole->json('data.*.source')));
        $fermetures = $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier?type=fermeture")->assertOk();
        $this->assertCount(1, $fermetures->json('data'));
        $this->getJson("/api/annees-scolaires/{$annee->id}/calendrier?type=zzz")->assertStatus(422);
    }

    public function test_modifier_une_entree_fwb_la_marque_modifiee_manuellement(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $entree = CalendrierScolaire::where('cle_fwb', 'vacances-automne-2026')->first();
        $this->actingAsRole('directeur');

        $this->putJson("/api/calendrier-scolaire/{$entree->id}", ['date_fin' => '2026-10-31'])
            ->assertOk()->assertJsonPath('data.date_fin', '2026-10-31')->assertJsonPath('data.modifie_manuellement', true)
            ->assertJsonPath('data.source', 'fwb');

        $this->putJson("/api/calendrier-scolaire/{$entree->id}", ['date_fin' => '2026-10-01'])->assertStatus(422);
    }

    public function test_supprimer_fwb_masque_et_supprimer_ecole_efface(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $fwb = CalendrierScolaire::where('cle_fwb', 'ferie-armistice-2026')->first();
        $ecole = CalendrierScolaire::factory()->create(['annee_scolaire_id' => $annee->id]);
        $this->actingAsRole('directeur');

        $this->deleteJson("/api/calendrier-scolaire/{$fwb->id}")->assertOk()->assertJson(['masque' => true]);
        $this->assertTrue($fwb->fresh()->masque);

        $this->deleteJson("/api/calendrier-scolaire/{$ecole->id}")->assertOk()->assertJson(['masque' => false]);
        $this->assertDatabaseMissing('calendrier_scolaire', ['id' => $ecole->id]);

        // Une entrée FWB masquée reste masquée aux imports suivants, et peut être rétablie.
        $this->importFwb($annee);
        $this->assertTrue($fwb->fresh()->masque);
        $this->putJson("/api/calendrier-scolaire/{$fwb->id}", ['masque' => false])->assertOk()->assertJsonPath('data.masque', false);
    }

    public function test_import_fwb_reserve_a_ladmin_et_idempotent(): void
    {
        $annee = $this->annee();

        $this->actingAsRole('directeur');
        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")->assertStatus(403);
        $this->assertSame(0, CalendrierScolaire::count());

        $this->actingAsRole('admin');
        $premier = $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")->assertOk();
        $this->assertSame(14, $premier->json('creees'));
        $this->assertSame(0, $premier->json('ignorees'));
        $this->assertFalse($premier->json('verifie'));
        $this->assertSame('https://www.enseignement.be/calendrier-scolaire', $premier->json('source_url'));

        $second = $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")->assertOk();
        $this->assertSame(0, $second->json('creees'));
        $this->assertSame(14, $second->json('ignorees'));
        $this->assertSame(14, CalendrierScolaire::count());
    }

    public function test_import_fwb_nechappe_ni_les_entrees_ecole_ni_les_modifications_manuelles(): void
    {
        $annee = $this->annee();
        $this->importFwb($annee);
        $entree = CalendrierScolaire::where('cle_fwb', 'vacances-automne-2026')->first();
        $entree->update(['date_fin' => '2026-10-31', 'modifie_manuellement' => true]);
        $ecole = CalendrierScolaire::factory()->create(['annee_scolaire_id' => $annee->id, 'libelle' => 'Mon entrée']);

        $resultat = $this->importFwb($annee);

        $this->assertSame(0, $resultat['creees']);
        $this->assertSame('2026-10-31', $entree->fresh()->date_fin->toDateString());
        $this->assertSame('Mon entrée', $ecole->fresh()->libelle);
    }

    public function test_import_fwb_422_sans_fichier_pour_cette_annee(): void
    {
        $annee = AnneeScolaire::factory()->avecPeriodes()->create(['libelle' => '2040-2041']);
        $this->actingAsRole('admin');

        $this->postJson("/api/annees-scolaires/{$annee->id}/calendrier/import-fwb")
            ->assertStatus(422)
            ->assertJsonPath('message', "Aucun calendrier FWB disponible pour l'année 2040-2041.");
    }

    public function test_commande_artisan_import_fwb_par_libelle_et_id(): void
    {
        $annee = $this->annee();

        $this->artisan('calendrier:import-fwb', ['annee' => '2026-2027'])->assertSuccessful();
        $this->assertSame(14, CalendrierScolaire::count());

        $this->artisan('calendrier:import-fwb', ['annee' => (string) $annee->id])->assertSuccessful();
        $this->assertSame(14, CalendrierScolaire::count());

        $this->artisan('calendrier:import-fwb', ['annee' => 'inconnue'])->assertFailed();
    }
}
