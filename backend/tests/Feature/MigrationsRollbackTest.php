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
    protected function tearDown(): void
    {
        // Laisse la base de test migrée pour les autres tests.
        Artisan::call('migrate:fresh');
        parent::tearDown();
    }

    /**
     * Annule toutes les migrations jusqu'à $migration incluse. Le nombre de steps est calculé depuis la table
     * `migrations` (et non codé en dur) : il reste juste quand de nouvelles migrations sont ajoutées.
     */
    private function rollbackJusqua(string $migration): void
    {
        $this->assertTrue(DB::table('migrations')->where('migration', $migration)->exists(), "Migration inconnue : {$migration}");
        $steps = DB::table('migrations')->where('migration', '>=', $migration)->count();

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]), Artisan::output());
        $this->assertFalse(DB::table('migrations')->where('migration', $migration)->exists(), "Migration non annulée : {$migration}");
    }

    public function test_migrate_reset_complet_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');

        // Tous les down() doivent passer (ex. clé étrangère retirée avant sa colonne).
        $this->assertSame(0, Artisan::call('migrate:reset'), Artisan::output());
        $this->assertSame(0, DB::table('migrations')->count());
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('timesheets'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertTrue(Schema::hasColumn('timesheets', 'pdf_generated_by'));
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
        $this->rollbackJusqua('2026_10_01_300000_drop_types_cours_tables');
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

        $this->rollbackJusqua('2026_10_01_100000_normalize_timesheet_statuts');
        // Hors enum d'origine : ramené à « valide »
        $this->assertSame('valide', DB::table('timesheets')->value('statut_validation'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        // La re-migration convertit « valide » en « confirme ».
        $this->assertSame('confirme', DB::table('timesheets')->value('statut_validation'));
    }

    public function test_rollback_des_migrations_t2_puis_remigration(): void
    {
        Artisan::call('migrate:fresh');

        $this->rollbackJusqua('2026_09_30_200000_create_professeur_classe_table');

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

        // On revient jusqu'à la première migration T1 incluse (toutes les migrations postérieures sont annulées).
        $this->rollbackJusqua('2026_09_30_100000_create_annees_scolaires_periodes_calendrier_classes_tables');

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

    public function test_cls02_rollback_recopie_vers_classes_puis_backfill_a_la_remigration(): void
    {
        Artisan::call('migrate:fresh');
        $this->assertTrue(Schema::hasTable('classe_periodes'));
        $this->assertTrue(Schema::hasTable('classe_periode_cours_historique'));
        $this->assertFalse(Schema::hasColumn('classes', 'cours_id'));
        $this->assertTrue(Schema::hasColumn('course_sessions', 'classe_periode_id'));

        $now = now();
        $coursId = DB::table('cours')->insertGetId(['titre' => 'Scratch', 'slug' => 'scratch', 'created_at' => $now, 'updated_at' => $now]);
        $anneeId = DB::table('annees_scolaires')->insertGetId(['libelle' => '2026-2027', 'date_debut' => '2026-08-24', 'date_fin' => '2027-07-02', 'created_at' => $now, 'updated_at' => $now]);
        $p1 = DB::table('periodes')->insertGetId(['annee_scolaire_id' => $anneeId, 'numero' => 1, 'date_debut' => '2026-08-24', 'date_fin' => '2027-02-19', 'created_at' => $now, 'updated_at' => $now]);
        $p2 = DB::table('periodes')->insertGetId(['annee_scolaire_id' => $anneeId, 'numero' => 2, 'date_debut' => '2027-02-22', 'date_fin' => '2027-07-02', 'created_at' => $now, 'updated_at' => $now]);
        $classeId = DB::table('classes')->insertGetId(['annee_scolaire_id' => $anneeId, 'jour_semaine' => 3, 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'statut' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $cp2 = DB::table('classe_periodes')->insertGetId(['classe_id' => $classeId, 'periode_id' => $p2, 'cours_id' => $coursId, 'date_premiere_session' => '2027-03-03', 'statut' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        $cp1 = DB::table('classe_periodes')->insertGetId(['classe_id' => $classeId, 'periode_id' => $p1, 'cours_id' => $coursId, 'date_premiere_session' => '2026-10-07', 'statut' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('course_sessions')->insert(['classe_id' => $classeId, 'classe_periode_id' => $cp1, 'seance_numero' => 1, 'bis_rang' => 0, 'date' => '2026-10-07', 'heure_debut' => '14:00', 'heure_fin' => '17:00', 'statut' => 'planifiee', 'created_at' => $now, 'updated_at' => $now]);

        $this->rollbackJusqua('2026_10_07_100000_create_classe_periodes_table');

        $this->assertFalse(Schema::hasTable('classe_periodes'));
        $classe = DB::table('classes')->first();
        $this->assertSame($coursId, (int) $classe->cours_id);
        $this->assertSame($p1, (int) $classe->periode_id, 'la période de plus petit numéro est recopiée');
        $this->assertSame('2026-10-07', substr((string) $classe->date_premiere_session, 0, 10));
        $this->assertFalse(Schema::hasColumn('course_sessions', 'classe_periode_id'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $cp = DB::table('classe_periodes')->get();
        $this->assertCount(1, $cp);
        $this->assertSame($p1, (int) $cp[0]->periode_id);
        $this->assertSame((int) $cp[0]->id, (int) DB::table('course_sessions')->value('classe_periode_id'));
        $this->assertFalse(Schema::hasColumn('classes', 'periode_id'));
    }
}
