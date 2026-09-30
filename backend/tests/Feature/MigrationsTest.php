<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_toutes_les_migrations_passent_sur_mysql(): void
    {
        foreach (['users', 'professeurs', 'cours', 'timesheets', 'classe_liens', 'course_sessions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
    }
}
