<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T1 : les sessions appartiennent désormais à une classe (ADR-0001).
 * Aucune donnée n'est migrée (décision direction) : l'ancien modèle Sprint 2
 * (course_recurrences, course_sessions, session_professors) est abandonné.
 * session_calendar_views est indépendante des sessions (préférences par utilisateur) : conservée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->detachTimesheets();

        Schema::dropIfExists('session_professors');
        Schema::dropIfExists('course_sessions');
        Schema::dropIfExists('course_recurrences');

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->unsignedTinyInteger('seance_numero'); // 1..14, fixe
            $table->unsignedTinyInteger('bis_rang')->default(0); // 0 = originale, 1 = bis...
            $table->foreignId('remplace_session_id')->nullable()->constrained('course_sessions')->nullOnDelete();
            $table->date('date');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu', 255)->nullable();
            $table->string('statut', 20)->default('planifiee'); // planifiee | en_cours | terminee | annulee
            $table->text('motif_annulation')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'seance_numero', 'bis_rang']);
            $table->index(['classe_id', 'date']);
            $table->index(['date', 'statut']);
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->detachTimesheets();

        Schema::dropIfExists('course_sessions');

        // Recréation (vide) de la structure Sprint 2.
        Schema::create('course_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->enum('type', ['weekly', 'biweekly', 'monthly'])->default('weekly');
            $table->string('jours_semaine', 50)->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu_defaut', 255)->nullable();
            $table->enum('statut', ['active', 'paused', 'archived'])->default('active');
            $table->timestamps();
            $table->index(['cours_id', 'statut']);
            $table->index(['date_debut', 'date_fin']);
        });

        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('course_recurrences')->nullOnDelete();
            $table->date('date_debut');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->text('titre')->nullable();
            $table->string('lieu', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('statut', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('motif_annulation')->nullable();
            $table->foreignId('professor_principal_id')->nullable()->constrained('professeurs')->nullOnDelete();
            $table->integer('nb_eleves_attendus')->default(0);
            $table->integer('nb_eleves_presentes')->nullable();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            $table->index(['cours_id', 'date_debut']);
            $table->index(['professor_principal_id', 'date_debut']);
            $table->index(['statut', 'date_debut']);
            $table->unique(['cours_id', 'date_debut', 'heure_debut']);
        });

        Schema::create('session_professors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->enum('role', ['principal', 'assistant', 'substitute', 'observer'])->default('principal');
            $table->boolean('present')->nullable();
            $table->text('motif_absence')->nullable();
            $table->timestamps();
            $table->index('course_session_id');
            $table->index(['professeur_id', 'created_at']);
            $table->unique(['course_session_id', 'professeur_id', 'role']);
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreign('course_session_id')->references('id')->on('course_sessions')->nullOnDelete();
        });
    }

    /** Retire la FK timesheets.course_session_id (la colonne et son index restent). */
    private function detachTimesheets(): void
    {
        if (! Schema::hasColumn('timesheets', 'course_session_id')) {
            return;
        }

        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeign(['course_session_id']);
        });

        // Les anciennes sessions sont abandonnées : les références deviennent nulles
        // (les saisies restent consultables via cours_id / date_prestation).
        DB::table('timesheets')->update(['course_session_id' => null]);
    }
};
