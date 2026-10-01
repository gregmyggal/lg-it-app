<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
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
}
