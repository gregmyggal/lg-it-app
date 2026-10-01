<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * migrate:fresh puis migrate:rollback propres — UNIQUEMENT sur lgit_test : le garde-fou de
 * Tests\TestCase (refreshApplication) refuse toute autre base avant l'exécution de ce test.
 * N'utilise pas RefreshDatabase (les DDL MySQL valident implicitement les transactions).
 */
class MigrationsRollbackTest extends TestCase
{
    protected function tearDown(): void
    {
        // Laisse la base de test migrée pour les autres tests.
        Artisan::call('migrate:fresh');
        parent::tearDown();
    }

    public function test_migrate_fresh_cree_le_schema_t1(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));

        foreach (['annees_scolaires', 'periodes', 'calendrier_scolaire', 'classes', 'course_sessions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table manquante : {$table}");
        }
        $this->assertFalse(Schema::hasTable('course_recurrences'));
        $this->assertTrue(Schema::hasColumn('course_sessions', 'classe_id'));
        $this->assertTrue(Schema::hasColumn('timesheets', 'course_session_id'));
        $this->assertTrue(Schema::hasTable('session_calendar_views'));
    }

    public function test_migrate_fresh_cree_le_schema_t2(): void
    {
        $this->assertSame(0, Artisan::call('migrate:fresh'));

        $this->assertTrue(Schema::hasTable('professeur_classe'));
        $this->assertTrue(Schema::hasTable('session_professors'));
        foreach (['origine', 'remplace', 'remplace_par_professeur_id', 'role'] as $colonne) {
            $this->assertTrue(Schema::hasColumn('session_professors', $colonne), "Colonne manquante : {$colonne}");
        }
        $this->assertFalse(Schema::hasTable('professeur_cours'));
    }

    public function test_rollback_des_migrations_t2_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 3]), Artisan::output());

        $this->assertFalse(Schema::hasTable('professeur_classe'));
        $this->assertFalse(Schema::hasTable('session_professors'));
        $this->assertTrue(Schema::hasTable('professeur_cours'));
        $this->assertTrue(Schema::hasTable('classes'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertTrue(Schema::hasTable('professeur_classe'));
        $this->assertFalse(Schema::hasTable('professeur_cours'));
    }

    public function test_rollback_des_migrations_t1_restaure_la_structure_sprint_2_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');

        // On revient sur les 3 migrations T2 puis les 2 migrations T1 (le rollback complet de l'historique
        // antérieur à CLS-01 échoue sur des down() plus anciens, hors périmètre).
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 5]), Artisan::output());

        // Structure Sprint 2 recréée (vide), tables T1 supprimées.
        $this->assertFalse(Schema::hasTable('classes'));
        $this->assertFalse(Schema::hasTable('annees_scolaires'));
        $this->assertTrue(Schema::hasTable('course_recurrences'));
        $this->assertTrue(Schema::hasTable('session_professors'));
        $this->assertTrue(Schema::hasColumn('course_sessions', 'cours_id'));
        $this->assertFalse(Schema::hasColumn('course_sessions', 'classe_id'));
        $this->assertTrue(Schema::hasColumn('timesheets', 'course_session_id'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertTrue(Schema::hasColumn('course_sessions', 'classe_id'));
        $this->assertFalse(Schema::hasTable('course_recurrences'));
    }
}
