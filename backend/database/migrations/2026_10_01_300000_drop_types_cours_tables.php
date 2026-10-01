<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T5 (ADR-0001 §4) : la notion de « type de cours » est supprimée — le catalogue de cours est organisé en classes,
 * et les droits reposent sur les assignations (T2). Aucune migration de données : les liaisons existantes disparaissent
 * avec les tables (plus aucun droit ne les utilisait). Pivots d'abord, puis `types_cours`.
 * Les types de FORMATION (autre notion) sont conservés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('professeur_type_cours');
        Schema::dropIfExists('cours_type_cours');
        Schema::dropIfExists('types_cours');
    }

    /** Recrée les trois tables, vides, telles que définies par les migrations d'origine. */
    public function down(): void
    {
        Schema::create('types_cours', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('professeur_type_cours', function (Blueprint $table) {
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('type_cours_id')->constrained('types_cours')->cascadeOnDelete();
            $table->primary(['professeur_id', 'type_cours_id']);
        });

        Schema::create('cours_type_cours', function (Blueprint $table) {
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('type_cours_id')->constrained('types_cours')->cascadeOnDelete();
            $table->primary(['cours_id', 'type_cours_id']);
        });
    }
};
