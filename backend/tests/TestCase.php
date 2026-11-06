<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou : exécuté juste après le démarrage de l'application et AVANT tout trait de test
     * (RefreshDatabase, etc.). Les tests ne doivent JAMAIS toucher la base de développement.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->assertSafeTestDatabase();
    }

    protected function assertSafeTestDatabase(): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '');

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException(
                "Tests refusés : la connexion « {$connection} » n'est pas MySQL (base de test = MySQL dédiée dans Docker)."
            );
        }

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Tests refusés : la base « {$database} » ne finit pas par _test. "
                .'Lancez `docker compose up -d db_test` et vérifiez backend/.env.testing et phpunit.xml.'
            );
        }

        if (! in_array($host, ['127.0.0.1', 'localhost', 'db_test'], true)) {
            throw new RuntimeException("Tests refusés : hôte de base de données inattendu « {$host} ».");
        }
    }
}
