<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Détermine les cours que le professeur est habilité à voir/modifier (isolation).
        Schema::create('professeur_type_cours', function (Blueprint $table) {
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('type_cours_id')->constrained('types_cours')->cascadeOnDelete();
            $table->primary(['professeur_id', 'type_cours_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professeur_type_cours');
    }
};
