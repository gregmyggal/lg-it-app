<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-01 T2 : l'assignation professeur → cours est remplacée par professeur_classe (aucune donnée à migrer). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('professeur_cours');
    }

    /** Recrée la table (vide) telle que définie par la migration d'origine. */
    public function down(): void
    {
        Schema::create('professeur_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->enum('role', ['principal', 'co-enseignant', 'remplaçant'])->default('co-enseignant');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->timestamps();

            $table->unique(['professeur_id', 'cours_id']);
            $table->index(['cours_id', 'date_fin']);
            $table->index(['professeur_id', 'date_fin']);
            $table->index(['role']);
        });
    }
};
