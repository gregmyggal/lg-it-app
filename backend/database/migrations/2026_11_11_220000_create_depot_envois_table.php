<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DEP-01 T3 : journal des envois du lien par e-mail. L'adresse du parent n'est jamais conservée en clair (version masquée seule). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depot_envois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partage_id')->constrained('depot_partages')->cascadeOnDelete();
            $table->string('destinataire_masque', 190);
            $table->foreignId('envoye_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('envoye_le');

            $table->index('partage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_envois');
    }
};
