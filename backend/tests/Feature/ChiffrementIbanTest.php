<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** RGPD-01 : IBAN chiffré au repos, absent des listes, exposé seulement aux gestionnaires de paie et au titulaire. */
class ChiffrementIbanTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private const IBAN = 'BE68539007547034';

    private function brut(int $id): ?string
    {
        return DB::table('professeurs')->where('id', $id)->value('compte_bancaire');
    }

    public function test_la_colonne_est_chiffree_en_base_et_dechiffree_par_le_modele(): void
    {
        $prof = Professeur::factory()->create(['compte_bancaire' => self::IBAN]);

        $brut = $this->brut($prof->id);
        $this->assertStringNotContainsString(self::IBAN, $brut);
        $this->assertStringNotContainsString('5390', $brut);
        $this->assertGreaterThan(34, strlen($brut));
        $this->assertSame(self::IBAN, Crypt::decryptString($brut));
        $this->assertSame(self::IBAN, $prof->fresh()->compte_bancaire);
    }

    public function test_la_commande_chiffre_les_valeurs_en_clair_et_est_idempotente(): void
    {
        $clair = Professeur::factory()->create();
        $deja = Professeur::factory()->create(['compte_bancaire' => 'BE71096123456769']);
        $vide = Professeur::factory()->create();
        DB::table('professeurs')->where('id', $clair->id)->update(['compte_bancaire' => self::IBAN]);
        $avant = $this->brut($deja->id);

        $this->artisan('professeurs:chiffrer-iban', ['--dry-run' => true])->expectsOutputToContain('1 IBAN à chiffrer')->assertSuccessful();
        $this->assertSame(self::IBAN, $this->brut($clair->id));

        $this->artisan('professeurs:chiffrer-iban')->expectsOutputToContain('1 IBAN chiffré(s), 1 déjà chiffré(s), 1 vide(s)')->assertSuccessful();
        $apres = $this->brut($clair->id);
        $this->assertNotSame(self::IBAN, $apres);
        $this->assertSame(self::IBAN, $clair->fresh()->compte_bancaire);
        $this->assertSame($avant, $this->brut($deja->id), 'Une valeur déjà chiffrée n\'est pas retouchée.');
        $this->assertNull($this->brut($vide->id));

        // Deuxième passage : rien à faire, valeurs inchangées.
        $this->artisan('professeurs:chiffrer-iban')->expectsOutputToContain('0 IBAN chiffré(s), 2 déjà chiffré(s)')->assertSuccessful();
        $this->assertSame($apres, $this->brut($clair->id));
    }

    public function test_la_liste_n_expose_jamais_l_iban_mais_son_masque(): void
    {
        $avec = Professeur::factory()->create(['compte_bancaire' => self::IBAN]);
        $sans = Professeur::factory()->create();

        foreach (['admin', 'directeur'] as $role) {
            $this->actingAsRole($role);
            $r = $this->getJson('/api/professeurs')->assertOk();
            $this->assertStringNotContainsString(self::IBAN, $r->getContent());
            $this->assertStringNotContainsString('5390', $r->getContent());

            $ligne = collect($r->json())->firstWhere('id', $avec->id);
            $this->assertArrayNotHasKey('compte_bancaire', $ligne);
            $this->assertSame('BE68 •••• •••• 7034', $ligne['compte_bancaire_masque']);
            $this->assertTrue($ligne['compte_bancaire_renseigne']);

            $vide = collect($r->json())->firstWhere('id', $sans->id);
            $this->assertFalse($vide['compte_bancaire_renseigne']);
            $this->assertNull($vide['compte_bancaire_masque']);
        }
    }

    public function test_l_iban_complet_est_dans_show_et_update_pour_le_staff(): void
    {
        $prof = Professeur::factory()->create(['compte_bancaire' => self::IBAN]);

        foreach (['admin', 'directeur'] as $role) {
            $this->actingAsRole($role);
            $this->getJson("/api/professeurs/{$prof->id}")->assertOk()
                ->assertJsonPath('compte_bancaire', self::IBAN)
                ->assertJsonPath('compte_bancaire_masque', 'BE68 •••• •••• 7034')
                ->assertJsonPath('compte_bancaire_renseigne', true);
            $this->putJson("/api/professeurs/{$prof->id}", ['telephone' => '0470'])->assertOk()
                ->assertJsonPath('compte_bancaire', self::IBAN);
        }
    }

    public function test_le_professeur_lit_son_iban_via_me_et_show_mais_pas_celui_d_un_autre(): void
    {
        $moi = Professeur::factory()->create(['compte_bancaire' => self::IBAN]);
        $autre = Professeur::factory()->create(['compte_bancaire' => 'BE71096123456769']);
        Sanctum::actingAs($moi->user);

        $this->getJson('/api/me')->assertOk()->assertJsonPath('professeur.compte_bancaire', self::IBAN);
        $this->getJson("/api/professeurs/{$moi->id}")->assertOk()->assertJsonPath('compte_bancaire', self::IBAN);
        $this->getJson("/api/professeurs/{$autre->id}")->assertForbidden();
        $this->getJson('/api/professeurs')->assertForbidden();
    }

    public function test_aucune_autre_serialisation_ne_divulgue_l_iban(): void
    {
        $prof = Professeur::factory()->create(['compte_bancaire' => self::IBAN]);
        $this->assertArrayNotHasKey('compte_bancaire', $prof->fresh()->toArray());
        $this->assertStringNotContainsString(self::IBAN, $prof->fresh()->toJson());

        // Login : le profil est renvoyé sans IBAN.
        $prof->user->forceFill(['password' => 'secret-pass-123'])->save();
        $r = $this->postJson('/api/login', ['email' => $prof->user->email, 'password' => 'secret-pass-123'])->assertOk();
        $this->assertStringNotContainsString(self::IBAN, $r->getContent());

        $this->actingAsRole('directeur');
        $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->assertOk();
        $this->assertStringNotContainsString(self::IBAN, $this->getJson('/api/timesheets/mois-synthese?annee=2026&mois=10')->getContent());
    }
}
