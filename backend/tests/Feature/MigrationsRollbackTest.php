<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * migrate:fresh puis migrate:rollback propres — UNIQUEMENT sur lgit_test : le garde-fou de
 * Tests\TestCase (refreshApplication) refuse toute autre base avant l'exécution de ce test.
 * N'utilise pas RefreshDatabase (les DDL MySQL valident implicitement les transactions).
 */
class MigrationsRollbackTest extends TestCase
{
    /** Nombre de migrations à annuler pour revenir AVANT la migration donnée (robuste à l'ajout de nouvelles migrations). */
    private function etapesDepuis(string $premiere): int
    {
        $noms = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'))->sort()->values();

        return $noms->count() - $noms->search($premiere);
    }

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

    public function test_t5_supprime_les_types_de_cours_et_conserve_les_types_de_formation(): void
    {
        Artisan::call('migrate:fresh');

        foreach (['types_cours', 'professeur_type_cours', 'cours_type_cours'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table encore présente : {$table}");
        }
        $this->assertTrue(Schema::hasTable('types_formation'));
        $this->assertTrue(Schema::hasTable('formation_type_formation'));

        // Rollback : les trois tables sont recréées (vides) ; puis re-migration propre.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $this->etapesDepuis('2026_10_01_200200_make_classe_liens_theme_nullable')]), Artisan::output());
        foreach (['types_cours', 'professeur_type_cours', 'cours_type_cours'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table non recréée : {$table}");
        }
        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertFalse(Schema::hasTable('types_cours'));
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

    public function test_rollback_des_migrations_t3_restaure_l_enum_des_statuts_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');
        DB::table('users')->insert(['id' => 1, 'name' => 'P', 'email' => 'p@t.test', 'password' => 'x', 'role' => 'professeur', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('professeurs')->insert(['id' => 1, 'user_id' => 1, 'prenom' => 'A', 'nom' => 'B', 'email' => 'p@t.test', 'statut' => 'actif', 'date_entree' => '2026-01-01', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('timesheets')->insert(['professeur_id' => 1, 'date_prestation' => '2026-10-01', 'nombre_heures' => 2, 'statut_validation' => 'confirme', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $this->etapesDepuis('2026_09_30_200200_drop_professeur_cours_table')]), Artisan::output());
        // Hors enum d'origine : ramené à « valide »
        $this->assertSame('valide', DB::table('timesheets')->value('statut_validation'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        // La re-migration convertit « valide » en « confirme ».
        $this->assertSame('confirme', DB::table('timesheets')->value('statut_validation'));
    }

    public function test_rollback_des_migrations_t2_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $this->etapesDepuis('2026_09_30_200000_create_professeur_classe_table')]), Artisan::output());

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

        // On revient sur la migration PROF-01, la migration T5, les 3 migrations T4, les 2 migrations T3, les 3 migrations T2 puis les 2 migrations T1 (le rollback complet de l'historique
        // antérieur à CLS-01 échoue sur des down() plus anciens, hors périmètre).
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $this->etapesDepuis('2026_09_29_create_professeur_cours_table')]), Artisan::output());

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
