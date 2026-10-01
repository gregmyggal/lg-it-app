<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T2 : professeurs d'une session (propagés depuis la classe ou ajoutés par un remplacement).
 * La table a été abandonnée en T1 ; aucune donnée à migrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('session_professors');

        Schema::create('session_professors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->string('role', 20)->default('co_enseignant'); // principal | co_enseignant | remplacant
            $table->string('origine', 20)->default('classe'); // classe | remplacement
            $table->boolean('remplace')->default(false); // le professeur a été remplacé sur cette session
            $table->foreignId('remplace_par_professeur_id')->nullable()->constrained('professeurs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_session_id', 'professeur_id']);
            $table->index('professeur_id');
        });
    }

    /** Supprime la table (la migration T1 recrée l'ancienne structure Sprint 2 en remontant). */
    public function down(): void
    {
        Schema::dropIfExists('session_professors');
    }
};
