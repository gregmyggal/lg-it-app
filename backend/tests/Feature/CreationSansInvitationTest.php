<?php

namespace Tests\Feature;

use App\Mail\InvitationCompteMail;
use App\Models\Professeur;
use App\Models\User;
use App\Notifications\NotificationTimesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** ADMIN-05 : création sans invitation, envoi différé / en lot, aucun email de notification avant mot de passe défini. */
class CreationSansInvitationTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function payload(array $o = []): array
    {
        return $o + ['login_email' => 'nouveau@example.com', 'prenom' => 'Ada', 'nom' => 'Lovelace',
            'email' => 'ada@example.com', 'date_entree' => '2026-09-01'];
    }

    private function notification(): NotificationTimesheet
    {
        return new NotificationTimesheet(NotificationTimesheet::MOIS_A_CONFIRMER, 'Titre', 'Message', '/timesheets', 1, 2026, 9);
    }

    public function test_creation_professeur_sans_envoi_n_envoie_aucun_email_et_reste_volontaire(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson('/api/professeurs', $this->payload(['envoyer_invitation' => false]))->assertCreated()
            ->assertJsonPath('mail_envoye', false)
            ->assertJsonPath('data.acces.statut', 'invitation_non_envoyee')
            ->assertJsonPath('data.acces.motif', 'volontaire')
            ->assertJsonPath('data.acces.a_relancer', false);

        Mail::assertNothingSent();
        $this->assertNull(User::where('email', 'nouveau@example.com')->first()->invitation_envoyee_le);
    }

    public function test_creation_professeur_par_defaut_envoie_toujours_l_invitation(): void
    {
        $this->actingAsRole('directeur');

        $this->postJson('/api/professeurs', $this->payload())->assertCreated()->assertJsonPath('mail_envoye', true);

        Mail::assertSent(InvitationCompteMail::class, 1);
    }

    public function test_creation_staff_sans_envoi(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/api/staff', ['name' => 'Jean', 'email' => 'jean@example.com', 'role' => 'directeur', 'envoyer_invitation' => false])
            ->assertCreated()->assertJsonPath('email_envoye', false)
            ->assertJsonPath('data.acces.statut', 'invitation_non_envoyee')
            ->assertJsonPath('data.acces.motif', 'volontaire');

        Mail::assertNothingSent();
    }

    public function test_envoi_differe_depuis_la_fiche_puis_statut_en_attente(): void
    {
        $this->actingAsRole('directeur');
        $id = $this->postJson('/api/professeurs', $this->payload(['envoyer_invitation' => false]))->json('data.id');

        $this->postJson("/api/professeurs/{$id}/envoyer-lien")->assertOk()
            ->assertJsonPath('mail_envoye', true)->assertJsonPath('data.acces.statut', 'invitation_en_attente');

        Mail::assertSent(InvitationCompteMail::class, 1);
    }

    public function test_echec_d_envoi_donne_le_motif_echec_et_a_relancer(): void
    {
        $prof = Professeur::factory()->create();
        $prof->user->forceFill(['invitation_echec_le' => now()])->save();

        $acces = app(\App\Services\AccesCompteService::class)->resumeAcces($prof->user->refresh());

        $this->assertSame('invitation_non_envoyee', $acces['statut']);
        $this->assertSame('echec', $acces['motif']);
        $this->assertTrue($acces['a_relancer']);
    }

    public function test_reactivation_sans_envoi(): void
    {
        $prof = Professeur::factory()->create(['statut' => 'inactif', 'date_sortie' => '2026-09-01']);
        $prof->user->forceFill(['mot_de_passe_defini_le' => now()])->save();
        $this->actingAsRole('directeur');

        $this->postJson("/api/professeurs/{$prof->id}/reactiver", ['envoyer_invitation' => false])->assertOk()
            ->assertJsonPath('mail_envoye', false)->assertJsonPath('data.acces.statut', 'invitation_non_envoyee');

        Mail::assertNothingSent();
        $this->assertNull($prof->user->refresh()->mot_de_passe_defini_le);
    }

    public function test_notification_sans_email_tant_que_le_mot_de_passe_n_est_pas_defini(): void
    {
        $sans = User::factory()->professeur()->create(['mot_de_passe_defini_le' => null]);
        $provisoire = User::factory()->professeur()->create(['must_change_password' => true, 'mot_de_passe_defini_le' => null]);
        $avec = User::factory()->professeur()->create(['mot_de_passe_defini_le' => now()]);

        $this->assertSame(['database'], $this->notification()->via($sans));
        $this->assertSame(['database'], $this->notification()->via($provisoire));
        $this->assertSame(['database', 'mail'], $this->notification()->via($avec));
    }

    public function test_lot_envoie_aux_comptes_non_envoyes_et_ignore_les_autres(): void
    {
        $this->actingAsRole('directeur');
        $a = Professeur::factory()->create();
        $b = Professeur::factory()->create();
        $dejaInvite = Professeur::factory()->create();
        $dejaInvite->user->forceFill(['invitation_envoyee_le' => now()])->save();

        $r = $this->postJson('/api/professeurs/envoyer-invitations', ['ids' => [$a->id, $b->id, $dejaInvite->id]])->assertOk();

        $this->assertSame(['envoye', 'envoye', 'ignore'], array_column($r->json('data'), 'resultat'));
        $this->assertSame('Déjà invité', $r->json('data.2.motif'));
        Mail::assertSent(InvitationCompteMail::class, 2);
    }

    public function test_lot_plafonne_a_25_et_reserve_a_la_direction(): void
    {
        $this->actingAsRole('directeur');
        $this->postJson('/api/professeurs/envoyer-invitations', ['ids' => range(1, 26)])->assertUnprocessable();

        $this->actingAsRole('professeur');
        $p = Professeur::factory()->create();
        $this->postJson('/api/professeurs/envoyer-invitations', ['ids' => [$p->id]])->assertForbidden();
    }

    public function test_lot_staff_reserve_a_l_admin(): void
    {
        $cible = User::factory()->directeur()->create(['statut' => 'actif']);

        $this->actingAsRole('directeur');
        $this->postJson('/api/staff/envoyer-invitations', ['ids' => [$cible->id]])->assertForbidden();

        $this->actingAsRole('admin');
        $this->postJson('/api/staff/envoyer-invitations', ['ids' => [$cible->id]])->assertOk()
            ->assertJsonPath('data.0.resultat', 'envoye');
    }
}
