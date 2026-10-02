<?php

namespace Tests\Feature;

use App\Mail\InvitationCompteMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\AccesToken;
use App\Models\Professeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** ADMIN-03 : invitation par email et lien de réinitialisation pour les professeurs (aligné sur ADMIN-02). */
class ProfesseurAccesTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function extraire(string $lien): array
    {
        parse_str(explode('#', $lien, 2)[1], $p);

        return [$p['email'], $p['token']];
    }

    private function lienEnvoye(string $classe): string
    {
        $lien = null;
        Mail::assertSent($classe, function ($m) use (&$lien) {
            $lien = $m->lien;

            return true;
        });

        return $lien;
    }

    private function creer(string $loginEmail = 'nouveau@example.com')
    {
        return $this->postJson('/api/professeurs', [
            'login_email' => $loginEmail, 'prenom' => 'Ada', 'nom' => 'Lovelace',
            'email' => 'contact@example.com', 'date_entree' => '2026-09-01',
        ]);
    }

    public function test_flux_complet_creation_lien_definition_et_connexion(): void
    {
        $this->actingAsRole('directeur');
        $id = $this->creer()->assertCreated()->json('data.id');
        $this->app['auth']->forgetGuards();

        [$email, $token] = $this->extraire($this->lienEnvoye(InvitationCompteMail::class));
        $this->postJson('/api/mot-de-passe/definir', [
            'email' => $email, 'token' => $token, 'password' => 'MotDePasse2026', 'password_confirmation' => 'MotDePasse2026',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => 'nouveau@example.com', 'password' => 'MotDePasse2026'])
            ->assertOk()->assertJsonPath('user.role', 'professeur')->assertJsonPath('user.must_change_password', false);
        $this->assertNotNull(Professeur::find($id)->user->mot_de_passe_defini_le);
    }

    public function test_echec_d_envoi_cree_le_professeur_et_renvoie_un_lien_de_repli(): void
    {
        $this->actingAsRole('admin');
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP KO'));

        $r = $this->creer()->assertCreated()
            ->assertJsonPath('mail_envoye', false)->assertJsonPath('data.acces.statut', 'invitation_non_envoyee');

        $this->extraire($r->json('lien'));
        $this->assertDatabaseHas('professeurs', ['email' => 'contact@example.com']);
    }

    public function test_renvoyer_l_invitation_puis_reinitialiser_selon_l_etat_du_compte(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertOk()
            ->assertJsonPath('mail_envoye', true)->assertJsonPath('data.acces.statut', 'invitation_en_attente');
        Mail::assertSent(InvitationCompteMail::class, fn ($m) => $m->hasTo($prof->user->email) && $m->validite === '72 h');

        $prof->user->forceFill(['mot_de_passe_defini_le' => now()])->save();
        $this->travel(2)->minutes();
        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertOk();
        Mail::assertSent(ReinitialisationMotDePasseMail::class, fn ($m) => $m->hasTo($prof->user->email) && $m->parDirection);
    }

    public function test_professeur_existant_a_mot_de_passe_provisoire_garde_son_mot_de_passe_jusqu_a_l_usage_du_lien(): void
    {
        $prof = Professeur::factory()->create();
        $prof->user->forceFill(['password' => 'provisoire-123', 'must_change_password' => true])->save();
        $this->actingAsRole('directeur');

        $this->getJson('/api/professeurs')->assertJsonPath('0.acces.statut', 'mot_de_passe_provisoire');

        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertOk();
        Mail::assertSent(ReinitialisationMotDePasseMail::class);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => $prof->user->email, 'password' => 'provisoire-123'])->assertOk();

        [$email, $token] = $this->extraire($this->lienEnvoye(ReinitialisationMotDePasseMail::class));
        $this->postJson('/api/mot-de-passe/definir', [
            'email' => $email, 'token' => $token, 'password' => 'Definitif2026', 'password_confirmation' => 'Definitif2026',
        ])->assertOk();

        $this->postJson('/api/login', ['email' => $email, 'password' => 'provisoire-123'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => $email, 'password' => 'Definitif2026'])
            ->assertOk()->assertJsonPath('user.must_change_password', false);
    }

    public function test_deuxieme_envoi_dans_la_minute_est_refuse(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');

        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertOk();
        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")
            ->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'Réessayez dans'));
    }

    public function test_un_professeur_desactive_ne_recoit_aucun_lien(): void
    {
        $prof = Professeur::factory()->create(['statut' => 'inactif', 'date_sortie' => '2026-09-01']);
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertStatus(409);
        $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertStatus(409);
        Mail::assertNothingSent();
    }

    public function test_generer_un_lien_a_transmettre(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');

        $r = $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertOk()
            ->assertJsonPath('data.acces.statut', 'invitation_en_attente');

        $this->extraire($r->json('lien'));
        Mail::assertNothingSent();
    }

    public function test_desactivation_et_changement_d_email_annulent_les_liens(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');
        $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertOk();
        $this->assertSame(1, AccesToken::where('user_id', $prof->user_id)->count());

        $this->putJson("/api/professeurs/{$prof->id}/compte", ['login_email' => 'autre@example.com'])->assertOk();
        $this->assertSame(0, AccesToken::where('user_id', $prof->user_id)->count());

        $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertOk();
        $this->postJson("/api/professeurs/{$prof->id}/desactiver")->assertOk();
        $this->assertSame(0, AccesToken::where('user_id', $prof->user_id)->count());
    }

    public function test_seuls_admin_et_directeur_envoient_un_lien_et_pas_au_staff(): void
    {
        $prof = Professeur::factory()->create();
        $staff = User::factory()->create(['role' => 'directeur', 'statut' => 'actif']);

        $this->actingAsRole('professeur');
        $this->postJson("/api/professeurs/{$prof->id}/envoyer-lien")->assertForbidden();
        $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertForbidden();

        $this->actingAsRole('directeur');
        $this->postJson("/api/staff/{$staff->id}/envoyer-lien")->assertForbidden();
    }

    public function test_l_ancien_endpoint_de_reinitialisation_avec_mot_de_passe_n_existe_plus(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('admin');

        $this->postJson("/api/professeurs/{$prof->id}/reinitialiser-mot-de-passe")->assertStatus(404);
    }

    public function test_la_liste_expose_le_statut_d_acces_et_a_relancer(): void
    {
        $nonEnvoye = Professeur::factory()->create(['nom' => 'Aaa']);
        $defini = Professeur::factory()->create(['nom' => 'Bbb']);
        $defini->user->forceFill(['mot_de_passe_defini_le' => now()])->save();
        $inactif = Professeur::factory()->create(['nom' => 'Ccc', 'statut' => 'inactif', 'date_sortie' => '2026-09-01']);
        $this->actingAsRole('directeur');

        $liste = collect($this->getJson('/api/professeurs')->assertOk()->json())->keyBy('id');

        $this->assertSame('invitation_non_envoyee', $liste[$nonEnvoye->id]['acces']['statut']);
        $this->assertSame('volontaire', $liste[$nonEnvoye->id]['acces']['motif']);
        $this->assertFalse($liste[$nonEnvoye->id]['acces']['a_relancer']); // ADMIN-05 : pas un échec
        $this->assertSame('mot_de_passe_defini', $liste[$defini->id]['acces']['statut']);
        $this->assertFalse($liste[$defini->id]['acces']['a_relancer']);
        $this->assertNull($liste[$inactif->id]['acces']['statut']);
    }

    public function test_la_liste_ne_fait_pas_une_requete_par_professeur(): void
    {
        $this->actingAsRole('directeur');
        Professeur::factory()->count(2)->create();
        DB::enableQueryLog();
        $this->getJson('/api/professeurs')->assertOk();
        $petit = count(DB::getQueryLog());

        Professeur::factory()->count(8)->create();
        DB::flushQueryLog();
        $this->getJson('/api/professeurs')->assertOk();

        $this->assertSame($petit, count(DB::getQueryLog()));
    }

    public function test_statut_en_attente_depuis_plus_de_trois_jours_est_a_relancer(): void
    {
        $prof = Professeur::factory()->create();
        $this->actingAsRole('directeur');
        $this->postJson("/api/professeurs/{$prof->id}/generer-lien")->assertOk();

        Carbon::setTestNow(now()->addDays(4));
        $this->getJson('/api/professeurs')->assertJsonPath('0.acces.a_relancer', true);
        Carbon::setTestNow();
    }
}
