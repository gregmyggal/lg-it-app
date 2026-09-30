<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_les_tests_utilisent_la_base_mysql_dediee(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringEndsWith('_test', config('database.connections.mysql.database'));
    }

    public function test_le_garde_fou_refuse_la_base_de_dev(): void
    {
        config(['database.connections.mysql.database' => 'lgitapp']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ne finit pas par _test');

        $this->assertSafeTestDatabase();
    }

    public function test_le_garde_fou_refuse_un_hote_inattendu(): void
    {
        config(['database.connections.mysql.host' => 'db']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('hôte de base de données inattendu');

        $this->assertSafeTestDatabase();
    }

    public function test_le_garde_fou_refuse_une_base_non_mysql(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("n'est pas MySQL");

        $this->assertSafeTestDatabase();
    }
}
