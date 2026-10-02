<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_professors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();

            // Rôle dans cette session
            $table->enum('role', ['principal', 'assistant', 'substitute', 'observer'])->default('principal');

            // Présence/Statut
            $table->boolean('present')->nullable();
            $table->text('motif_absence')->nullable();

            // Audit
            $table->timestamps();

            // Indexes & Constraints
            $table->index('course_session_id');
            $table->index(['professeur_id', 'created_at']);
            // Nom explicite : le nom auto-généré dépasse 64 caractères avec un DB_PREFIX de 8 caractères.
            $table->unique(['course_session_id', 'professeur_id', 'role'], 'session_professors_session_prof_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_professors');
    }
};
