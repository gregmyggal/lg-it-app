<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RGPD-01 : journal append-only des lectures de données sensibles (IBAN, fiches PDF). Identifiants seulement,
 * aucune donnée personnelle en clair. Pas de clé étrangère : la trace survit à la suppression d'un compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acces_donnees_sensibles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('professeur_id')->index();
            $table->string('action', 30);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acces_donnees_sensibles');
    }
};
