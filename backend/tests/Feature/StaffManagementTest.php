<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un admin et un directeur pour les tests
        $this->admin = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);
        $this->directeur = User::factory()->create(['role' => 'directeur', 'statut' => 'actif']);
    }

    // AC-1 : Créer un directeur
    public function test_admin_can_create_directeur()
    {
        $email = 'jean-' . time() . '@example.com';
        $response = $this->actingAs($this->admin)->postJson('/api/staff', [
            'name' => 'Jean Dupont',
            'email' => $email,
            'role' => 'directeur',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'password'])
            ->assertJsonPath('data.statut', 'actif')
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'directeur');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => 'directeur',
            'statut' => 'actif',
        ]);
    }

    // AC-2 : Erreur si email déjà utilisé
    public function test_cannot_create_with_duplicate_email()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/staff', [
            'name' => 'Another User',
            'email' => $this->directeur->email,
            'role' => 'directeur',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // AC-3 : Désactiver un directeur
    public function test_admin_can_deactivate_directeur()
    {
        $response = $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/desactiver");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'inactif');

        $this->directeur->refresh();
        $this->assertEquals('inactif', $this->directeur->statut);
        $this->assertNotNull($this->directeur->date_sortie);
    }

    // AC-4 : Directeur désactivé ne peut pas utiliser un token
    public function test_deactivated_directeur_cannot_use_token()
    {
        $this->directeur->update(['statut' => 'inactif', 'date_sortie' => now()]);

        // Essayer d'utiliser l'API avec le token du directeur désactivé
        $response = $this->actingAs($this->directeur)->getJson('/api/staff');

        // Le middleware EnsureCompteActif devrait refuser l'accès
        $response->assertStatus(403);
    }

    // AC-6 : Réactiver un directeur
    public function test_admin_can_reactivate_directeur()
    {
        $this->directeur->update(['statut' => 'inactif', 'date_sortie' => now()]);

        $response = $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/reactiver");

        $response->assertStatus(200)
            ->assertJsonPath('data.statut', 'actif')
            ->assertJsonStructure(['password']);

        $this->directeur->refresh();
        $this->assertEquals('actif', $this->directeur->statut);
        $this->assertNull($this->directeur->date_sortie);
    }

    // AC-7 : Admin ne peut pas se désactiver soi-même
    public function test_admin_cannot_deactivate_themselves()
    {
        $response = $this->actingAs($this->admin)->postJson("/api/staff/{$this->admin->id}/desactiver");

        $response->assertStatus(403);
    }

    // AC-11 : Lister staff (admins et directeurs)
    public function test_admin_can_list_staff()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/staff');

        $response->assertStatus(200)
            ->assertJsonIsArray();

        // Vérifier qu'il y a au moins l'admin et le directeur
        $data = $response->json();
        $this->assertGreaterThanOrEqual(2, count($data));

        // Vérifier que tous les users sont admin ou directeur
        foreach ($data as $user) {
            $this->assertContains($user['role'], ['admin', 'directeur']);
        }
    }

    // AC-12 : Professeur ne peut pas accéder à la gestion du staff
    public function test_professeur_cannot_access_staff()
    {
        $professeur = User::factory()->create(['role' => 'professeur']);

        $response = $this->actingAs($professeur)->getJson('/api/staff');

        $response->assertStatus(403);
    }

    // AC-13 : Directeur ne peut pas accéder à la gestion du staff
    public function test_directeur_cannot_access_staff()
    {
        $response = $this->actingAs($this->directeur)->getJson('/api/staff');

        $response->assertStatus(403);
    }

    public function test_can_reinitialize_password()
    {
        $response = $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/reinitialiser-mot-de-passe");

        $response->assertStatus(200)
            ->assertJsonStructure(['password']);

        $this->directeur->refresh();
        $this->assertTrue($this->directeur->must_change_password);
    }

    public function test_can_update_staff()
    {
        $newEmail = 'jp-' . time() . '@example.com';
        $response = $this->actingAs($this->admin)->putJson("/api/staff/{$this->directeur->id}", [
            'name' => 'Jean-Pierre Dupont',
            'email' => $newEmail,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jean-Pierre Dupont')
            ->assertJsonPath('data.email', $newEmail);
    }

    // T2 : Impact info endpoint fonctionne
    public function test_impact_info_endpoint_works()
    {
        $response = $this->actingAs($this->admin)->getJson("/api/staff/{$this->directeur->id}/impact-info");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['isLastAdmin', 'isLastDirecteur', 'timsheetsCount']]);
    }

    public function test_can_get_impact_info()
    {
        $response = $this->actingAs($this->admin)->getJson("/api/staff/{$this->directeur->id}/impact-info");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['isLastAdmin', 'isLastDirecteur', 'timsheetsCount']]);
    }

    // ---- Validation fonctionnelle : flux complet avec de vrais jetons ----

    private function loginAs(string $email, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    public function test_flux_complet_creation_premiere_connexion_et_changement_de_mot_de_passe()
    {
        $created = $this->actingAs($this->admin)->postJson('/api/staff', [
            'name' => 'Nouveau Dir', 'email' => 'nouveau.dir@example.com', 'role' => 'directeur',
        ])->assertStatus(201);
        $temp = $created->json('password');

        $login = $this->loginAs('nouveau.dir@example.com', $temp)->assertOk();
        $this->assertTrue($login->json('user.must_change_password'));
        $token = $login->json('token');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/me/mot-de-passe', [
            'current_password' => $temp,
            'password' => 'NouveauMdp123',
            'password_confirmation' => 'NouveauMdp123',
        ])->assertOk();

        $this->assertFalse(User::where('email', 'nouveau.dir@example.com')->first()->must_change_password);
        $this->loginAs('nouveau.dir@example.com', $temp)->assertStatus(422);
        $this->loginAs('nouveau.dir@example.com', 'NouveauMdp123')->assertOk();
    }

    public function test_desactivation_revoque_les_jetons_et_refuse_la_connexion()
    {
        $this->directeur->update(['password' => 'secret-123']);
        $token = $this->loginAs($this->directeur->email, 'secret-123')->assertOk()->json('token');

        $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/desactiver")->assertOk();
        $this->assertSame(0, $this->directeur->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer $token")->getJson('/api/me')->assertStatus(401);

        $this->loginAs($this->directeur->email, 'secret-123')
            ->assertStatus(403)
            ->assertJsonPath('code', 'compte_desactive');
        $this->assertSame(0, $this->directeur->tokens()->count());
    }

    public function test_changement_d_email_revoque_les_jetons_mais_pas_le_changement_de_nom()
    {
        $this->directeur->createToken('api');

        $this->actingAs($this->admin)->putJson("/api/staff/{$this->directeur->id}", ['name' => 'Autre Nom'])->assertOk();
        $this->assertSame(1, $this->directeur->tokens()->count());

        $this->actingAs($this->admin)->putJson("/api/staff/{$this->directeur->id}", ['email' => 'neuf@example.com'])->assertOk();
        $this->assertSame(0, $this->directeur->tokens()->count());
    }

    public function test_email_deja_pris_refuse_a_la_modification()
    {
        $this->actingAs($this->admin)
            ->putJson("/api/staff/{$this->directeur->id}", ['email' => $this->admin->email])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_ne_peut_pas_modifier_son_propre_role()
    {
        $this->actingAs($this->admin)
            ->putJson("/api/staff/{$this->admin->id}", ['role' => 'directeur'])
            ->assertStatus(403);
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_un_admin_peut_changer_le_role_d_un_autre_admin()
    {
        $autre = User::factory()->create(['role' => 'admin', 'statut' => 'actif']);

        $this->actingAs($this->admin)->putJson("/api/staff/{$autre->id}", ['role' => 'directeur'])
            ->assertOk()->assertJsonPath('data.role', 'directeur');
    }

    public function test_reactiver_un_compte_actif_est_refuse_et_ne_change_pas_le_mot_de_passe()
    {
        $hash = $this->directeur->password;

        $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/reactiver")->assertStatus(409);
        $this->assertSame($hash, $this->directeur->fresh()->password);
    }

    public function test_desactiver_un_compte_deja_inactif_est_refuse()
    {
        $this->directeur->update(['statut' => 'inactif', 'date_sortie' => '2026-09-01']);

        $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/desactiver")->assertStatus(409);
    }

    public function test_date_sortie_est_serialisee_en_date_simple()
    {
        $this->actingAs($this->admin)->postJson("/api/staff/{$this->directeur->id}/desactiver", ['date_sortie' => '2026-10-15'])
            ->assertOk()->assertJsonPath('data.date_sortie', '2026-10-15');
    }

    public function test_reinitialisation_invalide_l_ancien_mot_de_passe_et_les_jetons()
    {
        $this->directeur->update(['password' => 'ancien-123']);
        $this->loginAs($this->directeur->email, 'ancien-123')->assertOk();

        $new = $this->actingAs($this->admin)
            ->postJson("/api/staff/{$this->directeur->id}/reinitialiser-mot-de-passe")->json('password');

        $this->assertSame(0, $this->directeur->tokens()->count());
        $this->loginAs($this->directeur->email, 'ancien-123')->assertStatus(422);
        $this->loginAs($this->directeur->email, $new)->assertOk();
    }

    public function test_un_professeur_ne_peut_pas_etre_cible_via_l_api_staff()
    {
        $prof = User::factory()->create(['role' => 'professeur']);

        $this->actingAs($this->admin)->putJson("/api/staff/{$prof->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($this->admin)->postJson("/api/staff/{$prof->id}/desactiver")->assertStatus(403);
        $this->actingAs($this->admin)->postJson("/api/staff/{$prof->id}/reactiver")->assertStatus(403);
    }

    public function test_validations_de_creation_et_acces_non_authentifie()
    {
        $this->actingAs($this->admin)->postJson('/api/staff', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'role']);
        $this->actingAs($this->admin)->postJson('/api/staff', ['name' => 'x', 'email' => 'x@example.com', 'role' => 'professeur'])
            ->assertStatus(422)->assertJsonValidationErrors(['role']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/staff')->assertStatus(401);
    }

    public function test_filtres_statut_et_role()
    {
        $this->directeur->update(['statut' => 'inactif', 'date_sortie' => '2026-09-01']);

        $inactifs = $this->actingAs($this->admin)->getJson('/api/staff?statut=inactif')->json();
        $this->assertSame([$this->directeur->id], array_column($inactifs, 'id'));

        $admins = $this->actingAs($this->admin)->getJson('/api/staff?role=admin')->json();
        $this->assertSame(['admin'], array_unique(array_column($admins, 'role')));
    }
}
