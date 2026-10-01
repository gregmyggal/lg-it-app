<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-01 T2 : assignation d'un professeur à une classe (rôle indicatif, dates de validité). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professeur_classe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('role', 20)->default('co_enseignant'); // principal | co_enseignant | remplacant (indicatif)
            $table->date('date_debut'); // renseignée par le service (défaut : aujourd'hui)
            $table->date('date_fin')->nullable();
            $table->timestamps();

            $table->unique(['professeur_id', 'classe_id']);
            $table->index('classe_id');
            $table->index(['professeur_id', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professeur_classe');
    }
};
