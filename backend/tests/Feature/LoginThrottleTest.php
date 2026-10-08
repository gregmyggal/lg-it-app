<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** RGPD-01 : limiteur de POST /login (par email+IP et par IP). */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function tenter(string $email, string $mdp = 'mauvais', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/login', ['email' => $email, 'password' => $mdp]);
    }

    public function test_la_sixieme_tentative_par_minute_sur_un_meme_email_est_bloquee_puis_reprend(): void
    {
        $user = User::factory()->professeur()->create(['password' => 'bon-mot-de-passe']);

        for ($i = 0; $i < 5; $i++) {
            $this->tenter($user->email)->assertStatus(422);
        }
        $this->tenter($user->email)->assertStatus(429)->assertJsonPath('message', 'Trop de tentatives. Réessayez dans quelques minutes.')->assertHeader('Retry-After');
        // Même le bon mot de passe est refusé pendant le blocage.
        $this->tenter($user->email, 'bon-mot-de-passe')->assertStatus(429);

        $this->travel(61)->seconds();
        $this->tenter($user->email, 'bon-mot-de-passe')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_un_autre_email_depuis_la_meme_ip_n_est_pas_bloque_par_le_compteur_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->tenter('a@t.test')->assertStatus(422);
        }
        $this->tenter('a@t.test')->assertStatus(429);
        $this->tenter('b@t.test')->assertStatus(422);
        // L'email est insensible à la casse.
        $this->tenter('A@T.test')->assertStatus(429);
    }

    public function test_plafond_de_20_tentatives_par_minute_et_par_ip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->tenter("u{$i}@t.test")->assertStatus(422);
        }
        $this->tenter('nouveau@t.test')->assertStatus(429);
        // Une autre IP n'est pas affectée.
        $this->tenter('nouveau@t.test', ip: '10.0.0.2')->assertStatus(422);
    }
}
