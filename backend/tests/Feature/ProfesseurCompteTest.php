<?php

namespace Tests\Feature;

use App\Mail\InvitationProfesseurMail;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** PROF-01 : création, désactivation, réactivation et compte de connexion des professeurs. */
class ProfesseurCompteTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    private function payload(array $o = []): array
    {
        return $o + ['login_email' => 'nouveau@example.com', 'prenom' => 'Ada', 'nom' => 'Lovelace',
            'email' => 'ada@example.com', 'date_entree' => '2026-09-01'];
    }

    public function test_creation_genere_mot_de_passe_provisoire_et_envoie_invitation(): void
    {
        Mail::fake();
        $this->actingAsRole('directeur');

        $r = $this->postJson('/api/professeurs', $this->payload())->assertCreated()
            ->assertJsonPath('data.statut', 'actif')->assertJsonPath('mail_envoye', true);

        $user = User::where('email', 'nouveau@example.com')->first();
        $this->assertTrue($user->must_change_password);
        $this->assertSame('professeur', $user->role);
        $this->assertNotEmpty($r->json('mot_de_passe'));
        Mail::assertSent(InvitationProfesseurMail::class, fn ($m) => $m->hasTo('ada@example.com') && $m->motDePasse === $r->json('mot_de_passe'));
    }

    public function test_email_de_connexion_deja_pris_est_refuse(): void
    {
        $this->actingAsRole('admin');
        User::factory()->create(['email' => 'nouveau@example.com']);

        $this->postJson('/api/professeurs', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('login_email');
    }

    public function test_un_professeur_ne_peut_ni_creer_ni_desactiver(): void
    {
        $cible = Professeur::factory()->create();
        $this->actingAsRole('professeur');

        $this->postJson('/api/professeurs', $this->payload())->assertForbidden();
        $this->postJson("/api/professeurs/{$cible->id}/desactiver")->assertForbidden();
    }

    public function test_desactivation_termine_les_assignations_revoque_les_tokens_et_garde_lhistorique(): void
    {
        $prof = Professeur::factory()->create();
        $classe = $this->classeAvecSessions($this->annee());
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $prof);
        $prof->user->createToken('api');
        $this->actingAsRole('directeur');

        $this->getJson("/api/professeurs/{$prof->id}/impact-desactivation")->assertOk()->assertJsonPath('data.classes_actives', 1);

        $this->postJson("/api/professeurs/{$prof->id}/desactiver")->assertOk()
            ->assertJsonPath('data.statut', 'inactif')->assertJsonPath('assignations_terminees', 1);

        $prof->refresh();
        $this->assertNotNull($prof->date_sortie);
        $this->assertSame(0, $prof->user->tokens()->count());
        $this->assertSame(now('Europe/Brussels')->toDateString(), ProfesseurClasse::where('professeur_id', $prof->id)->first()->date_fin->toDateString());
        $this->assertSame(1, $prof->assignations()->count());
    }

    public function test_option_conserver_les_assignations(): void
    {
        $prof = Professeur::factory()->create();
        $classe = $this->classeAvecSessions($this->annee());
        app(ClasseProfesseurAssignmentService::class)->assigner($classe, $prof);
        $this->actingAsRole('admin');

        $this->postJson("/api/professeurs/{$prof->id}/desactiver", ['terminer_assignations' => false])
            ->assertOk()->assertJsonPath('assignations_terminees', 0);
    }

    public function test_connexion_refusee_si_desactive_uniquement_avec_bon_mot_de_passe(): void
    {
        $prof = Professeur::factory()->create(['statut' => 'inactif']);

        $this->postJson('/api/login', ['email' => $prof->user->email, 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('code', 'compte_desactive');
        $this->postJson('/api/login', ['email' => $prof->user->email, 'password' => 'faux'])
            ->assertUnprocessable();
    }

    public function test_token_existant_dun_professeur_desactive_est_refuse(): void
    {
        $prof = Professeur::factory()->create(['statut' => 'inactif']);
        \Laravel\Sanctum\Sanctum::actingAs($prof->user);

        $this->getJson('/api/me')->assertForbidden()->assertJsonPath('code', 'compte_desactive');
    }

    public function test_un_professeur_desactive_nest_plus_assignable(): void
    {
        $prof = Professeur::factory()->create(['statut' => 'inactif']);
        $classe = $this->classeAvecSessions($this->annee());
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$prof->id}/classes", ['classe_id' => $classe->id])->assertStatus(409);
    }

    public function test_reactivation_regenere_un_mot_de_passe(): void
    {
        Mail::fake();
        $prof = Professeur::factory()->create(['statut' => 'inactif', 'date_sortie' => '2026-09-30']);
        $this->actingAsRole('directeur');

        $r = $this->postJson("/api/professeurs/{$prof->id}/reactiver")->assertOk()->assertJsonPath('data.statut', 'actif');

        $this->assertNull($prof->refresh()->date_sortie);
        $this->assertTrue($prof->user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($r->json('mot_de_passe'), $prof->user->password));
        Mail::assertSent(InvitationProfesseurMail::class);
    }

    public function test_reinitialisation_et_changement_demail_revoquent_les_tokens(): void
    {
        Mail::fake();
        $prof = Professeur::factory()->create();
        $prof->user->createToken('api');
        $autre = User::factory()->create(['email' => 'pris@example.com']);
        $this->actingAsRole('directeur');

        $this->putJson("/api/professeurs/{$prof->id}/compte", ['login_email' => $autre->email])->assertUnprocessable();
        $this->putJson("/api/professeurs/{$prof->id}/compte", ['login_email' => 'neuf@example.com'])->assertOk();
        $this->assertSame('neuf@example.com', $prof->user->refresh()->email);
        $this->assertSame(0, $prof->user->tokens()->count());

        $this->postJson("/api/professeurs/{$prof->id}/reinitialiser-mot-de-passe")->assertOk()->assertJsonStructure(['mot_de_passe']);
        $this->assertTrue($prof->user->refresh()->must_change_password);
    }

    public function test_suppression_refusee_avec_donnees_liees_acceptee_sinon(): void
    {
        $avecHeures = Professeur::factory()->create();
        Timesheet::create(['professeur_id' => $avecHeures->id, 'date_prestation' => '2026-10-01', 'nombre_heures' => 2]);
        $vide = Professeur::factory()->create();
        $this->actingAsRole('admin');

        $this->deleteJson("/api/professeurs/{$avecHeures->id}")->assertStatus(409);
        $this->deleteJson("/api/professeurs/{$vide->id}")->assertNoContent();
        $this->assertDatabaseMissing('professeurs', ['id' => $vide->id]);
    }

    public function test_filtre_statut_sur_la_liste(): void
    {
        Professeur::factory()->create();
        Professeur::factory()->create(['statut' => 'inactif']);
        $this->actingAsRole('directeur');

        $this->assertCount(1, $this->getJson('/api/professeurs?statut=inactif')->json());
        $this->assertCount(2, $this->getJson('/api/professeurs')->json());
    }

    public function test_changement_de_mot_de_passe_leve_lobligation(): void
    {
        $user = User::factory()->create(['role' => 'professeur']);
        $user->forceFill(['must_change_password' => true])->save();
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/me/mot-de-passe', ['current_password' => 'password', 'password' => 'nouveau-mdp-1', 'password_confirmation' => 'nouveau-mdp-1'])
            ->assertOk()->assertJsonPath('must_change_password', false);
    }
}
