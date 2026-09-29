<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot table pour assignation fine de professeurs à des cours spécifiques.
     *
     * Support multi-professeur par cours:
     * - Un cours peut avoir 1+ professeurs (principal + co-enseignants + remplaçants)
     * - Un professeur peut être assigné à plusieurs cours
     * - Chaque assignation a un rôle et des dates de validité
     */
    public function up(): void
    {
        Schema::create('professeur_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')
                ->constrained('professeurs')
                ->cascadeOnDelete();
            $table->foreignId('cours_id')
                ->constrained('cours')
                ->cascadeOnDelete();

            // Rôle du professeur dans ce cours
            $table->enum('role', ['principal', 'co-enseignant', 'remplaçant'])
                ->default('co-enseignant');

            // Dates d'assignation (permet gestion des absences, remplaçants temporaires)
            $table->date('date_debut');
            $table->date('date_fin')->nullable(); // NULL = assignation permanente

            $table->timestamps();

            // Contrainte d'unicité : un professeur ne peut être assigné qu'une fois par cours
            // (mais peut avoir plusieurs rôles temporaires via date_fin)
            $table->unique(['professeur_id', 'cours_id']);

            // Index pour requêtes courantes
            $table->index(['cours_id', 'date_fin']); // "Quels profs pour ce cours aujourd'hui?"
            $table->index(['professeur_id', 'date_fin']); // "Quels cours pour ce prof aujourd'hui?"
            $table->index(['role']); // "Filtrer par rôle"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professeur_cours');
    }
};
