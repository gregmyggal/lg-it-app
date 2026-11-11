<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DEP-01 T2 : partage aux parents = sélection de fichiers + token (hash pour la recherche, copie chiffrée pour l'envoi différé). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depot_partages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('libelle', 60)->nullable();
            $table->char('token_hash', 64)->unique();
            // Effacé à la révocation et à l'expiration (purge) : voir ADR 0003.
            $table->text('token_chiffre')->nullable();
            $table->dateTime('expire_le');
            $table->dateTime('revoque_le')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('nb_ouvertures')->default(0);
            $table->dateTime('derniere_ouverture_le')->nullable();
            $table->timestamps();

            $table->index(['classe_id', 'expire_le']);
        });

        Schema::create('depot_partage_fichiers', function (Blueprint $table) {
            $table->foreignId('partage_id')->constrained('depot_partages')->cascadeOnDelete();
            $table->foreignId('fichier_id')->constrained('depot_fichiers')->cascadeOnDelete();
            $table->primary(['partage_id', 'fichier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_partage_fichiers');
        Schema::dropIfExists('depot_partages');
    }
};
