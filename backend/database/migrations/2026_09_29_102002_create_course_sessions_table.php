<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('recurrence_id')->nullable()->constrained('course_recurrences')->nullOnDelete();

            // Dates et horaires
            $table->date('date_debut');
            $table->time('heure_debut');
            $table->time('heure_fin');

            // Métadonnées
            $table->text('titre')->nullable();
            $table->string('lieu', 255)->nullable();
            $table->text('description')->nullable();

            // Statuts
            $table->enum('statut', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('motif_annulation')->nullable();

            // Suivi
            $table->foreignId('professor_principal_id')->nullable()->constrained('professeurs')->nullOnDelete();
            $table->integer('nb_eleves_attendus')->default(0);
            $table->integer('nb_eleves_presentes')->nullable();

            // Audit
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();

            // Indexes
            $table->index(['cours_id', 'date_debut']);
            $table->index(['professor_principal_id', 'date_debut']);
            $table->index(['statut', 'date_debut']);
            $table->unique(['cours_id', 'date_debut', 'heure_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sessions');
    }
};
