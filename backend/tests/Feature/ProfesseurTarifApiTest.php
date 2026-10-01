<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** Gestion des tarifs horaires : droits, chaînage automatique des périodes, modification et suppression. */
class ProfesseurTarifApiTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private function creer(Professeur $prof, string $debut, $eur = 15, ?string $fin = null)
    {
        return $this->postJson("/api/professeurs/{$prof->id}/tarifs", ['tarif_horaire_eur' => $eur, 'date_debut' => $debut, 'date_fin' => $fin]);
    }

    private function fin(int $id): ?string
    {
        return ProfesseurTarif::find($id)->date_fin?->toDateString();
    }

    public function test_admin_et_directeur_gerent_les_tarifs_le_professeur_non(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');
        $id = $this->creer($prof, '2026-09-01')->assertCreated()->assertJsonPath('tarif_horaire_eur', '15.00')->json('id');
        $this->putJson("/api/professeurs/{$prof->id}/tarifs/{$id}", ['tarif_horaire_eur' => 16])->assertOk();
        $this->deleteJson("/api/professeurs/{$prof->id}/tarifs/{$id}")->assertNoContent();

        $this->actingAsRole('admin');
        $this->creer($prof, '2026-09-01')->assertCreated();

        $this->actingAsRole('professeur');
        $this->creer($prof, '2027-01-01')->assertForbidden();
        $this->getJson("/api/professeurs/{$prof->id}/tarifs")->assertForbidden();
    }

    public function test_un_nouveau_tarif_arrete_le_precedent_a_sa_date_de_debut(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $a = $this->creer($prof, '2026-09-01', 15)->json('id');

        $b = $this->creer($prof, '2027-01-01', 18)->assertCreated()->json('id');

        $this->assertSame('2027-01-01', $this->fin($a));
        $this->assertNull($this->fin($b));
        $this->assertSame('15.00', (string) ProfesseurTarif::effectiveAt($prof->id, new \DateTime('2026-12-31'))->tarif_horaire_eur);
        $this->assertSame('18.00', (string) ProfesseurTarif::effectiveAt($prof->id, new \DateTime('2027-01-01'))->tarif_horaire_eur);
    }

    public function test_un_tarif_insere_dans_le_passe_est_borne_par_le_suivant(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $suivant = $this->creer($prof, '2027-01-01', 18)->json('id');

        $ancien = $this->creer($prof, '2026-09-01', 15)->assertCreated()->json('id');

        $this->assertSame('2027-01-01', $this->fin($ancien));
        $this->assertNull($this->fin($suivant));
    }

    public function test_deux_tarifs_ne_peuvent_pas_commencer_le_meme_jour(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $this->creer($prof, '2026-09-01')->assertCreated();

        $this->creer($prof, '2026-09-01', 20)->assertUnprocessable()->assertJsonValidationErrors('date_debut');
    }

    public function test_fin_anterieure_au_debut_refusee(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');

        $this->creer($prof, '2026-09-01', 15, '2026-08-01')->assertUnprocessable()->assertJsonValidationErrors('date_fin');
    }

    public function test_modification_du_montant_seul_et_des_dates(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $id = $this->creer($prof, '2026-09-01')->json('id');

        $this->putJson("/api/professeurs/{$prof->id}/tarifs/{$id}", ['tarif_horaire_eur' => 16.5])
            ->assertOk()->assertJsonPath('tarif_horaire_eur', '16.50');
        $this->putJson("/api/professeurs/{$prof->id}/tarifs/{$id}", ['tarif_horaire_eur' => 16.5, 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30'])
            ->assertOk();
        $this->assertSame('2027-06-30', $this->fin($id));
        $this->putJson("/api/professeurs/{$prof->id}/tarifs/{$id}", ['date_fin' => null])->assertOk();
        $this->assertNull($this->fin($id));
    }

    public function test_cloture_d_un_tarif(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $id = $this->creer($prof, '2026-09-01')->json('id');

        $this->postJson("/api/professeurs/{$prof->id}/tarifs/{$id}/terminate", ['date_fin' => '2027-01-01'])->assertOk();
        $this->assertSame('2027-01-01', $this->fin($id));
    }

    public function test_suppression_d_un_tarif_et_isolation_par_professeur(): void
    {
        $prof = Professeur::factory()->create();
        $autre = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $id = $this->creer($prof, '2026-09-01')->json('id');

        $this->deleteJson("/api/professeurs/{$autre->id}/tarifs/{$id}")->assertNotFound();
        $this->deleteJson("/api/professeurs/{$prof->id}/tarifs/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('professeur_tarifs', ['id' => $id]);
    }

    public function test_supprimer_le_dernier_tarif_rouvre_le_precedent(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');
        $a = $this->creer($prof, '2026-09-01', 15)->json('id');
        $b = $this->creer($prof, '2027-01-01', 18)->json('id');
        $this->assertSame('2027-01-01', $this->fin($a));

        $this->deleteJson("/api/professeurs/{$prof->id}/tarifs/{$b}")->assertNoContent();

        $this->assertNull($this->fin($a));
    }
}
