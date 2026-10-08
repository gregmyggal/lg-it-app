<?php

namespace Tests\Feature;

use App\Models\Professeur;
use App\Models\User;
use App\Support\Installer\ConfigurationProduction;
use App\Support\Installer\InstallToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PrepareScolarite;
use Tests\TestCase;

/** RGPD-01 : en-têtes de sécurité, garde-fous de configuration production, logs sans PII. */
class DurcissementConfigTest extends TestCase
{
    use PrepareScolarite;
    use RefreshDatabase;

    public function test_les_en_tetes_de_securite_sont_presents_sans_hsts_en_http(): void
    {
        $r = $this->getJson('/api/me')->assertUnauthorized();

        $r->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertFalse($r->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_uniquement_en_https(): void
    {
        $this->getJson('https://localhost/api/me')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_les_valeurs_dangereuses_sont_refusees_en_production_seulement(): void
    {
        $this->assertSame([], ConfigurationProduction::violations(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'MAIL_MAILER' => 'smtp', 'SESSION_ENCRYPT' => 'true']));
        $this->assertCount(1, ConfigurationProduction::violations(['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'MAIL_MAILER' => 'smtp', 'SESSION_ENCRYPT' => 'true']));
        $this->assertCount(1, ConfigurationProduction::violations(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'MAIL_MAILER' => 'log', 'SESSION_ENCRYPT' => 'true']));
        $this->assertCount(1, ConfigurationProduction::violations(['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'MAIL_MAILER' => 'smtp', 'SESSION_ENCRYPT' => 'false']));
        $this->assertSame([], ConfigurationProduction::violations(['APP_ENV' => 'local', 'APP_DEBUG' => 'true', 'MAIL_MAILER' => 'log', 'SESSION_ENCRYPT' => 'false']));
    }

    public function test_l_installeur_refuse_le_mailer_log_en_production(): void
    {
        $token = InstallToken::issue(1);
        try {
            $donnees = [
                'db_host' => 'invalide.test', 'db_port' => 3306, 'db_database' => 'x', 'db_username' => 'x', 'db_password' => 'x',
                'app_name' => 'T', 'app_url' => 'https://exemple.test', 'app_timezone' => 'Europe/Brussels',
                'mail_mailer' => 'log', 'mail_from_address' => 'a@exemple.test',
            ];

            $this->withHeader('X-Install-Token', $token)->postJson('/install/run/env', $donnees + ['app_env' => 'production'])
                ->assertStatus(422)->assertJsonPath('ok', false);
            $r = $this->withHeader('X-Install-Token', $token)->postJson('/install/run/env', $donnees + ['app_env' => 'production']);
            $this->assertStringContainsString('MAIL_MAILER=log est interdit en production', $r->json('message'));
        } finally {
            InstallToken::revoke();
        }
    }

    public function test_l_env_example_est_sur_par_defaut(): void
    {
        $env = (string) file_get_contents(base_path('.env.example'));
        foreach (['APP_DEBUG=false', 'LOG_LEVEL=warning', 'LOG_STACK=daily', 'LOG_DAILY_DAYS=14', 'SESSION_ENCRYPT=true', 'SESSION_SECURE_COOKIE=true', 'MAIL_MAILER=smtp'] as $ligne) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($ligne, '/').'$/m', $env, $ligne);
        }
        // Les valeurs de dev ne sont jamais actives.
        $this->assertDoesNotMatchRegularExpression('/^(APP_DEBUG=true|MAIL_MAILER=log)$/m', $env);
    }

    public function test_la_suppression_d_un_professeur_ne_journalise_ni_nom_ni_motif(): void
    {
        $prof = Professeur::factory()->create(['prenom' => 'Zoé', 'nom' => 'Uniquenom']);
        $admin = $this->actingAsRole('admin');
        $contexte = null;
        Log::shouldReceive('info')->once()->withArgs(function ($message, $ctx) use (&$contexte) {
            $contexte = $ctx;

            return true;
        });

        app(\App\Services\ProfesseurCompteService::class)->supprimer($prof, $admin, 'Motif libre confidentiel');

        $this->assertSame($prof->id, $contexte['professeur_id']);
        $this->assertArrayNotHasKey('nom', $contexte);
        $this->assertArrayNotHasKey('motif', $contexte);
        $this->assertStringNotContainsString('Uniquenom', json_encode($contexte));
        $this->assertStringNotContainsString('confidentiel', json_encode($contexte));
    }
}
