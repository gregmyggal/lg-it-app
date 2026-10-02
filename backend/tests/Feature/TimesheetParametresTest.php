<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Rules\Iban;
use App\Services\TimesheetParametreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** TS-00 : plafonds de défraiement par année et IBAN du professeur. */
class TimesheetParametresTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private const IBAN = 'BE68 5390 0754 7034';

    public function test_directeur_et_admin_lisent_et_modifient_le_plafond_avec_historique(): void
    {
        foreach (['directeur', 'admin'] as $role) {
            $this->actingAsRole($role);
            $this->putJson('/api/timesheet-parametres/2027', ['plafond_journalier_eur' => 45.5, 'plafond_annuel_eur' => 1800])
                ->assertOk()->assertJsonPath('data.plafond_journalier_eur', 45.5)->assertJsonPath('data.herite', false);
        }

        $this->getJson('/api/timesheet-parametres/2027')
            ->assertOk()
            ->assertJsonPath('data.plafond_annuel_eur', 1800)
            ->assertJsonCount(2, 'historique');
    }

    public function test_le_forfait_de_deplacement_est_parametrable_par_annee(): void
    {
        $service = app(TimesheetParametreService::class);
        $this->assertSame(7.5, $service->fraisDeplacement(2026));

        $this->actingAsRole('directeur');
        $this->putJson('/api/timesheet-parametres/2027', ['plafond_journalier_eur' => 45, 'plafond_annuel_eur' => 1800, 'frais_deplacement_eur' => 8.25])->assertOk();
        $this->assertSame(8.25, $service->fraisDeplacement(2027));
        // Sans valeur fournie, le forfait existant est conservé.
        $this->putJson('/api/timesheet-parametres/2027', ['plafond_journalier_eur' => 46, 'plafond_annuel_eur' => 1800])->assertOk();
        $this->assertSame(8.25, $service->fraisDeplacement(2027));
    }

    public function test_professeur_recoit_403_et_invite_401(): void
    {
        $this->actingAsRole('professeur');
        $this->getJson('/api/timesheet-parametres/2026')->assertForbidden();
        $this->putJson('/api/timesheet-parametres/2026', ['plafond_journalier_eur' => 50, 'plafond_annuel_eur' => 2000])->assertForbidden();
    }

    public function test_validation_du_plafond(): void
    {
        $this->actingAsRole('admin');
        $this->putJson('/api/timesheet-parametres/2027', ['plafond_journalier_eur' => 0, 'plafond_annuel_eur' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('plafond_journalier_eur');
        $this->putJson('/api/timesheet-parametres/2027', ['plafond_journalier_eur' => 50, 'plafond_annuel_eur' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('plafond_annuel_eur');
    }

    public function test_une_annee_sans_parametre_herite_de_la_precedente(): void
    {
        $s = app(TimesheetParametreService::class);
        $this->actingAsRole('admin');
        $this->putJson('/api/timesheet-parametres/2030', ['plafond_journalier_eur' => 50, 'plafond_annuel_eur' => 2000])->assertOk();

        $this->assertSame(50.0, $s->plafondJournalier(2032));
        $this->assertTrue($s->pour(2032)['herite']);
        $this->assertSame(44.02, $s->plafondJournalier(2019)); // avant tout paramétrage : défaut historique
    }

    public function test_le_lissage_utilise_le_plafond_parametre(): void
    {
        $this->assertSame(
            app(TimesheetParametreService::class)->plafondJournalier((int) date('Y')),
            44.02
        );
    }

    public function test_iban_enregistre_normalise_par_le_staff(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');

        $this->putJson("/api/professeurs/{$prof->id}", ['compte_bancaire' => strtolower(self::IBAN)])->assertOk();
        $this->assertSame('BE68539007547034', $prof->fresh()->compte_bancaire);
        $this->getJson("/api/professeurs/{$prof->id}")->assertJsonPath('compte_bancaire', 'BE68539007547034');

        $this->putJson("/api/professeurs/{$prof->id}", ['compte_bancaire' => null])->assertOk();
        $this->assertNull($prof->fresh()->compte_bancaire);
    }

    public function test_iban_invalide_refuse(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');

        $this->putJson("/api/professeurs/{$prof->id}", ['compte_bancaire' => 'BE00 0000 0000 0000'])
            ->assertStatus(422)->assertJsonValidationErrors('compte_bancaire');
    }

    public function test_un_professeur_ne_peut_pas_modifier_un_iban(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('professeur');

        $this->putJson("/api/professeurs/{$prof->id}", ['compte_bancaire' => self::IBAN])->assertForbidden();
    }

    public function test_regle_iban(): void
    {
        $this->assertTrue(Iban::estValide('BE68539007547034'));
        $this->assertFalse(Iban::estValide('BE68539007547035'));
        $this->assertFalse(Iban::estValide('1234'));
    }
}
