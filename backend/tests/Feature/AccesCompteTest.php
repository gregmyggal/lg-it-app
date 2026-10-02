<?php

namespace Tests\Feature;

use App\Mail\InvitationCompteMail;
use App\Mail\MotDePasseModifieMail;
use App\Mail\ReinitialisationMotDePasseMail;
use App\Models\AccesToken;
use App\Models\Professeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

/** ADMIN-02 : invitation par email, réinitialisation et « Mot de passe oublié ». */
class AccesCompteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);
    }

    /** Récupère token + email depuis le lien envoyé (fragment d'URL). */
    private function extraire(string $lien): array
    {
        $this->assertStringContainsString('/definir-mot-de-passe#token=', $lien);
        parse_str(explode('#', $lien, 2)[1], $p);

        return [$p['email'], $p['token']];
    }

    private function lienEnvoye(string $classe): string
    {
        $lien = null;
        Mail::assertSent($classe, function ($mail) use (&$lien) {
            $lien = $mail->lien;

            return true;
        });

        return $lien;
    }

    private function definir(string $email, string $token, string $pw = 'NouveauMdp123', ?string $conf = null)
    {
        return $this->postJson('/api/mot-de-passe/definir', [
            'email' => $email, 'token' => $token, 'password' => $pw, 'password_confirmation' => $conf ?? $pw,
        ]);
    }

    private function creerDirecteur(string $email = 'nouveau@example.com'): array
    {
        $r = $this->actingAs($this->admin)->postJson('/api/staff', ['name' => 'Nouveau', 'email' => $email, 'role' => 'directeur'])
            ->assertStatus(201);
        $this->app['auth']->forgetGuards();

        return [$r->json('data.id'), $this->lienEnvoye(InvitationCompteMail::class)];
    }

    // ---- Création : invitation, plus de mot de passe ----

    public function test_creation_envoie_une_invitation_sans_mot_de_passe()
    {
        $r = $this->actingAs($this->admin)->postJson('/api/staff', ['name' => 'Nouveau', 'email' => 'nouveau@example.com', 'role' => 'admin'])
            ->assertStatus(201)
            ->assertJsonPath('email_envoye', true)
            ->assertJsonPath('data.acces.statut', 'invitation_en_attente')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('lien');

        Mail::assertSent(InvitationCompteMail::class, fn ($m) => $m->hasTo('nouveau@example.com') && $m->role === 'administrateur' && $m->validite === '72 h');
        $user = User::find($r->json('data.id'));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->invitation_envoyee_le);
        $this->assertSame(1, AccesToken::where('user_id', $user->id)->where('type', 'invitation')->count());
    }

    public function test_le_token_n_est_stocke_que_sous_forme_de_hash()
    {
        [, $lien] = $this->creerDirecteur();
        [, $token] = $this->extraire($lien);

        $this->assertDatabaseMissing('acces_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('acces_tokens', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_le_compte_cree_n_est_pas_accessible_sans_le_lien()
    {
        $this->creerDirecteur();

        $this->postJson('/api/login', ['email' => 'nouveau@example.com', 'password' => 'password'])->assertStatus(422);
    }

    public function test_echec_d_envoi_cree_le_compte_et_renvoie_un_lien_de_repli()
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP KO'));

        $r = $this->actingAs($this->admin)->postJson('/api/staff', ['name' => 'Nouveau', 'email' => 'nouveau@example.com', 'role' => 'directeur'])
            ->assertStatus(201)
            ->assertJsonPath('email_envoye', false)
            ->assertJsonPath('data.acces.statut', 'invitation_non_envoyee');

        $this->extraire($r->json('lien'));
        $this->assertDatabaseHas('users', ['email' => 'nouveau@example.com']);
    }

    // ---- Parcours complet : lien -> mot de passe -> connexion ----

    public function test_flux_complet_invitation_definition_et_connexion()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);

        $this->postJson('/api/mot-de-passe/verifier', ['email' => $email, 'token' => $token])->assertOk()->assertJsonPath('valide', true);
        // La vérification ne consomme pas le lien (prévisualiseurs de liens, antivirus).
        $this->postJson('/api/mot-de-passe/verifier', ['email' => $email, 'token' => $token])->assertOk();

        $this->definir($email, $token)->assertOk();

        $user = User::find($id);
        $this->assertNotNull($user->mot_de_passe_defini_le);
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, AccesToken::where('user_id', $id)->count());
        Mail::assertSent(MotDePasseModifieMail::class, fn ($m) => $m->hasTo($email));

        $this->postJson('/api/login', ['email' => $email, 'password' => 'NouveauMdp123'])->assertOk()
            ->assertJsonPath('user.must_change_password', false);
    }

    public function test_le_lien_est_a_usage_unique()
    {
        [, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);

        $this->definir($email, $token)->assertOk();
        $this->definir($email, $token, 'AutreMdp12345')->assertStatus(410)->assertJsonPath('code', 'lien_invalide');
        $this->postJson('/api/mot-de-passe/verifier', ['email' => $email, 'token' => $token])->assertStatus(410);
    }

    public function test_definir_revoque_toutes_les_sessions()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);
        User::find($id)->createToken('api');

        $this->definir($email, $token)->assertOk();

        $this->assertSame(0, User::find($id)->tokens()->count());
    }

    public function test_validation_du_mot_de_passe()
    {
        [, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);

        $this->definir($email, $token, 'court')->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->definir($email, $token, 'MotDePasse123', 'Different123')->assertStatus(422)->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'La confirmation du champ mot de passe ne correspond pas.');
        // Une erreur de validation ne consomme pas le lien.
        $this->definir($email, $token)->assertOk();
    }

    public function test_lien_expire_falsifie_ou_d_un_autre_compte_donne_le_meme_message()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);
        $autre = User::factory()->create(['role' => 'directeur', 'statut' => 'actif']);

        foreach ([['email' => $email, 'token' => 'faux'], ['email' => $autre->email, 'token' => $token], ['email' => 'inconnu@example.com', 'token' => $token]] as $cas) {
            $this->postJson('/api/mot-de-passe/verifier', $cas)->assertStatus(410)->assertExactJson(['message' => 'Ce lien n\'est plus valable.', 'code' => 'lien_invalide']);
        }

        Carbon::setTestNow(now()->addHours(73));
        $this->postJson('/api/mot-de-passe/verifier', ['email' => $email, 'token' => $token])->assertStatus(410);
        $this->definir($email, $token)->assertStatus(410);
        Carbon::setTestNow();
    }

    public function test_un_nouvel_envoi_annule_le_lien_precedent()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $ancien] = $this->extraire($lien);
        RateLimiter::clear('acces-envoi:'.$id);

        $this->actingAs($this->admin)->postJson("/api/staff/$id/envoyer-lien")->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/mot-de-passe/verifier', ['email' => $email, 'token' => $ancien])->assertStatus(410);
        $this->assertSame(1, AccesToken::where('user_id', $id)->count());
    }

    // ---- Actions admin sur la fiche ----

    public function test_renvoyer_l_invitation_tant_que_le_mot_de_passe_n_est_pas_defini()
    {
        [$id] = $this->creerDirecteur();
        RateLimiter::clear('acces-envoi:'.$id);
        Carbon::setTestNow(now()->addHours(80));

        $this->actingAs($this->admin)->postJson('/api/staff')->assertStatus(422); // sanity: la route reste protégée
        $this->actingAs($this->admin)->getJson('/api/staff')->assertOk()
            ->assertJsonFragment(['statut' => 'invitation_expiree']);

        $this->actingAs($this->admin)->postJson("/api/staff/$id/envoyer-lien")
            ->assertOk()->assertJsonPath('email_envoye', true)->assertJsonPath('data.acces.statut', 'invitation_en_attente');
        Carbon::setTestNow();
    }

    public function test_lien_de_reinitialisation_garde_l_ancien_mot_de_passe_valide_jusqu_a_l_usage()
    {
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'password' => 'ancien-123', 'mot_de_passe_defini_le' => now()]);
        $dir->createToken('api');

        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")
            ->assertOk()->assertJsonPath('email_envoye', true);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, fn ($m) => $m->hasTo($dir->email) && $m->validite === '72 h');
        $this->assertSame(1, $dir->tokens()->count());
        $this->postJson('/api/login', ['email' => $dir->email, 'password' => 'ancien-123'])->assertOk();

        [$email, $token] = $this->extraire($this->lienEnvoye(ReinitialisationMotDePasseMail::class));
        $this->definir($email, $token)->assertOk();
        $this->postJson('/api/login', ['email' => $dir->email, 'password' => 'ancien-123'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => $dir->email, 'password' => 'NouveauMdp123'])->assertOk();
    }

    public function test_deuxieme_envoi_dans_la_minute_est_refuse()
    {
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);

        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")
            ->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'Réessayez dans'));
    }

    public function test_un_echec_d_envoi_ne_bloque_pas_le_reessai()
    {
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP KO'));
        $r = $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")->assertOk()->assertJsonPath('email_envoye', false);
        $this->extraire($r->json('lien'));

        $this->assertSame(0, $this->app->make(\App\Services\AccesCompteService::class)->attenteEnvoi($dir));
    }

    public function test_generer_un_lien_a_transmettre_marque_l_invitation_comme_envoyee()
    {
        $id = User::factory()->create(['role' => 'directeur', 'statut' => 'actif'])->id;

        $r = $this->actingAs($this->admin)->postJson("/api/staff/$id/generer-lien")->assertOk()
            ->assertJsonPath('data.acces.statut', 'invitation_en_attente');
        [$email, $token] = $this->extraire($r->json('lien'));

        Mail::assertNothingSent();
        $this->definir($email, $token)->assertOk();
    }

    public function test_aucun_envoi_pour_un_compte_desactive()
    {
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'inactif', 'date_sortie' => '2026-09-01']);

        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")->assertStatus(409);
        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/generer-lien")->assertStatus(409);
        Mail::assertNothingSent();
    }

    public function test_seul_un_admin_peut_envoyer_un_lien()
    {
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'actif']);
        $autre = User::factory()->create(['role' => 'directeur', 'statut' => 'actif']);

        $this->actingAs($dir)->postJson("/api/staff/{$autre->id}/envoyer-lien")->assertStatus(403);
        $this->actingAs($dir)->postJson("/api/staff/{$autre->id}/generer-lien")->assertStatus(403);
    }

    public function test_desactivation_annule_les_liens_et_la_reactivation_renvoie_une_invitation()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);

        $this->actingAs($this->admin)->postJson("/api/staff/$id/desactiver")->assertOk();
        $this->assertSame(0, AccesToken::where('user_id', $id)->count());
        $this->definir($email, $token)->assertStatus(410);

        Mail::fake();
        $r = $this->actingAs($this->admin)->postJson("/api/staff/$id/reactiver")->assertOk()
            ->assertJsonPath('email_envoye', true)
            ->assertJsonPath('data.acces.statut', 'invitation_en_attente')
            ->assertJsonMissingPath('password');
        Mail::assertSent(InvitationCompteMail::class, fn ($m) => $m->hasTo($email));
    }

    public function test_reactivation_invalide_l_ancien_mot_de_passe()
    {
        $dir = User::factory()->create([
            'role' => 'directeur', 'statut' => 'inactif', 'date_sortie' => '2026-09-01',
            'password' => 'ancien-123', 'mot_de_passe_defini_le' => now(),
        ]);

        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/reactiver")->assertOk();

        $this->postJson('/api/login', ['email' => $dir->email, 'password' => 'ancien-123'])->assertStatus(422);
        $this->assertNull($dir->fresh()->mot_de_passe_defini_le);
    }

    public function test_changement_d_email_annule_les_liens_en_cours()
    {
        [$id, $lien] = $this->creerDirecteur();
        [$email, $token] = $this->extraire($lien);

        $this->actingAs($this->admin)->putJson("/api/staff/$id", ['email' => 'autre@example.com'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->definir($email, $token)->assertStatus(410);
        $this->assertSame(0, AccesToken::where('user_id', $id)->count());
    }

    public function test_statuts_d_acces_calcules_par_le_backend()
    {
        $svc = $this->app->make(\App\Services\AccesCompteService::class);
        $make = fn (array $a) => User::factory()->create(array_merge(['role' => 'directeur', 'statut' => 'actif'], $a));

        $this->assertSame('mot_de_passe_defini', $svc->statutAcces($make(['mot_de_passe_defini_le' => now()])));
        $this->assertSame('mot_de_passe_provisoire', $svc->statutAcces($make(['must_change_password' => true])));
        $this->assertSame('invitation_non_envoyee', $svc->statutAcces($make(['must_change_password' => false])));
        $this->assertNull($svc->statutAcces($make(['statut' => 'inactif', 'date_sortie' => '2026-09-01'])));
    }

    public function test_a_relancer_selon_le_statut_et_l_anciennete()
    {
        $svc = $this->app->make(\App\Services\AccesCompteService::class);
        $dir = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'must_change_password' => false]);

        $this->assertFalse($svc->resumeAcces($dir)['a_relancer']);           // jamais envoyée (volontaire, ADMIN-05)

        $this->actingAs($this->admin)->postJson("/api/staff/{$dir->id}/envoyer-lien")->assertOk();
        $this->assertFalse($svc->resumeAcces($dir->fresh())['a_relancer']);  // en attente, récente

        Carbon::setTestNow(now()->addDays(4));
        $this->assertTrue($svc->resumeAcces($dir->fresh())['a_relancer']);   // en attente depuis > 3 jours / expirée
        Carbon::setTestNow();

        $ok = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);
        $this->assertFalse($svc->resumeAcces($ok)['a_relancer']);
    }

    // ---- « Mot de passe oublié » (tous rôles) ----

    public function test_oubli_reponse_identique_et_email_uniquement_pour_un_compte_actif()
    {
        $actif = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);
        $inactif = User::factory()->create(['role' => 'directeur', 'statut' => 'inactif', 'date_sortie' => '2026-09-01']);

        $reponses = [];
        foreach ([$actif->email, $inactif->email, 'inconnu@example.com'] as $email) {
            $r = $this->postJson('/api/mot-de-passe/oublie', ['email' => $email])->assertStatus(202);
            $reponses[] = $r->getContent();
        }
        $this->assertCount(1, array_unique($reponses));

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 1);
        Mail::assertSent(ReinitialisationMotDePasseMail::class, fn ($m) => $m->hasTo($actif->email) && $m->validite === '60 minutes');
    }

    public function test_oubli_fonctionne_pour_un_professeur_actif_mais_pas_inactif()
    {
        $prof = User::factory()->create(['role' => 'professeur']);
        Professeur::create(['user_id' => $prof->id, 'prenom' => 'A', 'nom' => 'B', 'email' => 'ab@example.com', 'statut' => 'actif', 'date_entree' => '2026-09-01']);
        $off = User::factory()->create(['role' => 'professeur']);
        Professeur::create(['user_id' => $off->id, 'prenom' => 'C', 'nom' => 'D', 'email' => 'cd@example.com', 'statut' => 'inactif', 'date_entree' => '2026-09-01']);

        $this->postJson('/api/mot-de-passe/oublie', ['email' => $prof->email])->assertStatus(202);
        $this->postJson('/api/mot-de-passe/oublie', ['email' => $off->email])->assertStatus(202);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 1);
        Mail::assertSent(ReinitialisationMotDePasseMail::class, fn ($m) => $m->hasTo($prof->email));
    }

    public function test_oubli_un_email_par_minute_et_par_compte()
    {
        $u = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);

        $this->postJson('/api/mot-de-passe/oublie', ['email' => $u->email])->assertStatus(202);
        $this->postJson('/api/mot-de-passe/oublie', ['email' => $u->email])->assertStatus(202);

        Mail::assertSent(ReinitialisationMotDePasseMail::class, 1);
    }

    public function test_oubli_limite_le_debit_par_ip_avec_un_message_generique()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mot-de-passe/oublie', ['email' => "x$i@example.com"])->assertStatus(202);
        }

        $this->postJson('/api/mot-de-passe/oublie', ['email' => 'x9@example.com'])
            ->assertStatus(429)->assertExactJson(['message' => 'Trop de tentatives. Réessayez dans quelques minutes.']);
    }

    public function test_oubli_valide_le_format_de_l_email()
    {
        $this->postJson('/api/mot-de-passe/oublie', ['email' => 'pasunemail'])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/mot-de-passe/oublie', [])->assertStatus(422);
    }

    public function test_lien_d_un_compte_desactive_apres_envoi_est_refuse()
    {
        $u = User::factory()->create(['role' => 'directeur', 'statut' => 'actif', 'mot_de_passe_defini_le' => now()]);
        $this->postJson('/api/mot-de-passe/oublie', ['email' => $u->email])->assertStatus(202);
        [$email, $token] = $this->extraire($this->lienEnvoye(ReinitialisationMotDePasseMail::class));

        $u->update(['statut' => 'inactif', 'date_sortie' => '2026-10-01']);

        $this->definir($email, $token)->assertStatus(410);
    }

    public function test_la_version_texte_de_l_email_contient_un_lien_non_echappe()
    {
        [, $lien] = $this->creerDirecteur("d'artagnan@example.com");

        Mail::assertSent(InvitationCompteMail::class, function ($m) use ($lien) {
            $texte = view('mail.acces.invitation-text', get_object_vars($m))->render();
            $this->assertStringContainsString($lien, $texte);
            $this->assertStringNotContainsString('&amp;', $texte);

            return true;
        });
    }

    public function test_le_contenu_des_emails_ne_contient_aucun_mot_de_passe()
    {
        [, $lien] = $this->creerDirecteur();
        Mail::assertSent(InvitationCompteMail::class, function ($m) {
            $html = $m->render();
            $this->assertStringContainsString('Définir mon mot de passe', $html);
            $this->assertStringNotContainsString('Mot de passe provisoire', $html);

            return true;
        });
    }
}
