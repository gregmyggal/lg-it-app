<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique(); // ex. « 2026-2027 »
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('active'); // brouillon | active | archivee
            $table->timestamps();
        });

        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero'); // 1 | 2
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();

            $table->unique(['annee_scolaire_id', 'numero']);
        });

        Schema::create('calendrier_scolaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('type', 20); // vacances | ferie | fermeture
            $table->string('libelle');
            $table->string('source', 10)->default('ecole'); // fwb | ecole
            $table->boolean('masque')->default(false);
            // Identifiant stable de l'entrée dans le fichier FWB (idempotence de l'import).
            $table->string('cle_fwb', 100)->nullable();
            // Entrée FWB modifiée à la main : l'import ne la touche plus.
            $table->boolean('modifie_manuellement')->default(false);
            $table->timestamps();

            $table->index(['annee_scolaire_id', 'date_debut']);
            $table->unique(['annee_scolaire_id', 'cle_fwb']);
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->restrictOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periodes')->restrictOnDelete();
            $table->unsignedTinyInteger('jour_semaine'); // 1 = lundi .. 7 = dimanche
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu', 255)->nullable();
            $table->date('date_premiere_session');
            $table->string('statut', 20)->default('active'); // active | terminee | archivee
            $table->timestamps();

            $table->index(['annee_scolaire_id', 'periode_id']);
            $table->index('cours_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('calendrier_scolaire');
        Schema::dropIfExists('periodes');
        Schema::dropIfExists('annees_scolaires');
    }
};
