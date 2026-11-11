<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DEP-01 T1 : espaces de dépôt (stage entier ou séance) et fichiers déposés. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depot_espaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            // NULL = espace du stage entier.
            $table->foreignId('course_session_id')->nullable()->constrained('course_sessions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['classe_id', 'course_session_id']);
        });

        Schema::create('depot_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_espace_id')->constrained('depot_espaces')->cascadeOnDelete();
            $table->string('nom_original');
            $table->string('chemin');
            $table->string('mime', 100);
            $table->unsignedBigInteger('taille');
            $table->string('libelle', 60)->nullable();
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['depot_espace_id', 'libelle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_fichiers');
        Schema::dropIfExists('depot_espaces');
    }
};
