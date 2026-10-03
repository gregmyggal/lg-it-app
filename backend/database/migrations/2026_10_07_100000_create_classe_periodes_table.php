<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-02 : une classe porte 1 ou 2 périodes (« périodes de classe »). Le cours, la période et la date de
 * démarrage quittent `classes` ; chaque session se rattache à sa période de classe.
 * Données existantes : une classe_periodes par classe, sessions rattachées. down() recopie vers `classes`
 * (la période de plus petit numéro) ; une classe à deux périodes perd donc sa seconde période au rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->foreignId('cours_id')->constrained('cours')->restrictOnDelete();
            $table->date('date_premiere_session');
            $table->string('statut', 20)->default('active'); // active | annulee
            $table->text('motif_annulation')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'periode_id'], 'classe_periodes_classe_periode_unique');
            $table->index('cours_id');
        });

        DB::statement('INSERT INTO classe_periodes (classe_id, periode_id, cours_id, date_premiere_session, statut, created_at, updated_at)
            SELECT id, periode_id, cours_id, date_premiere_session, \'active\', NOW(), NOW() FROM classes');

        Schema::table('course_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('classe_periode_id')->nullable()->after('classe_id');
        });

        DB::statement('UPDATE course_sessions cs JOIN classe_periodes cp ON cp.classe_id = cs.classe_id SET cs.classe_periode_id = cp.id');

        Schema::table('course_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('classe_periode_id')->nullable(false)->change();
            $table->foreign('classe_periode_id')->references('id')->on('classe_periodes')->restrictOnDelete();
            $table->unique(['classe_periode_id', 'seance_numero', 'bis_rang'], 'course_sessions_periode_seance_bis_unique');
            $table->dropUnique(['classe_id', 'seance_numero', 'bis_rang']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->index('annee_scolaire_id'); // la FK s'appuyait sur l'index composite supprimé ci-dessous
        });
        Schema::table('classes', function (Blueprint $table) {
            $table->dropForeign(['cours_id']);
            $table->dropForeign(['periode_id']);
            $table->dropIndex(['annee_scolaire_id', 'periode_id']);
            $table->dropIndex(['cours_id']);
        });
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['cours_id', 'periode_id', 'date_premiere_session']);
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('cours_id')->nullable()->after('id');
            $table->unsignedBigInteger('periode_id')->nullable()->after('annee_scolaire_id');
            $table->date('date_premiere_session')->nullable()->after('lieu');
        });

        // Période de plus petit numéro de chaque classe.
        DB::statement('UPDATE classes c
            JOIN (
                SELECT cp.classe_id, MIN(p.numero) AS numero
                FROM classe_periodes cp JOIN periodes p ON p.id = cp.periode_id
                GROUP BY cp.classe_id
            ) m ON m.classe_id = c.id
            JOIN classe_periodes cp2 ON cp2.classe_id = c.id
            JOIN periodes p2 ON p2.id = cp2.periode_id AND p2.numero = m.numero
            SET c.cours_id = cp2.cours_id, c.periode_id = cp2.periode_id, c.date_premiere_session = cp2.date_premiere_session');

        Schema::table('course_sessions', function (Blueprint $table) {
            $table->unique(['classe_id', 'seance_numero', 'bis_rang']);
            $table->dropForeign(['classe_periode_id']);
            $table->dropUnique('course_sessions_periode_seance_bis_unique');
            $table->dropColumn('classe_periode_id');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedBigInteger('cours_id')->nullable(false)->change();
            $table->unsignedBigInteger('periode_id')->nullable(false)->change();
            $table->date('date_premiere_session')->nullable(false)->change();
            $table->foreign('cours_id')->references('id')->on('cours')->restrictOnDelete();
            $table->foreign('periode_id')->references('id')->on('periodes')->restrictOnDelete();
            $table->index(['annee_scolaire_id', 'periode_id']);
            $table->index('cours_id');
        });
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['annee_scolaire_id']);
        });

        Schema::dropIfExists('classe_periodes');
    }
};
