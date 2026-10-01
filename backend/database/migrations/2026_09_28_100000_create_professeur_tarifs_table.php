<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professeur_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->decimal('tarif_horaire_eur', 8, 2);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->timestamps();

            // Index pour recherches: "quel tarif valide pour ce professeur à cette date?"
            // Nom explicite : le nom auto-généré dépasse 64 caractères avec un DB_PREFIX de 8 caractères.
            $table->index(['professeur_id', 'date_debut', 'date_fin'], 'professeur_tarifs_prof_periode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professeur_tarifs');
    }
};
