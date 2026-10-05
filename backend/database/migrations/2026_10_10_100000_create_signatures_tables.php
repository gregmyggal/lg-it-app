<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIG-01 : signature du professeur (image réutilisable), signatures de mois scellées (preuve immuable) et paramètres
 * généraux du site (vérification publique des signatures).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_specimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('type', 12); // nom | dessin | initiales
            $table->string('texte', 60)->nullable();
            $table->string('police', 40)->nullable();
            $table->string('couleur', 7);
            $table->string('chemin');
            $table->char('sha256', 64);
            $table->timestamp('consenti_at');
            $table->timestamps();
        });

        Schema::create('timesheet_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 16)->unique();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->string('statut', 12)->default('valide'); // valide | remplacee
            $table->timestamp('signed_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->longText('payload')->nullable(); // JSON canonique scellé, octet pour octet
            $table->char('content_hash', 64);
            $table->string('seal', 100)->nullable(); // Ed25519, base64
            $table->string('key_id', 40);
            $table->string('specimen_type', 12);
            $table->string('specimen_chemin');
            $table->boolean('verification_publique')->default(false);
            $table->timestamp('anonymisee_at')->nullable();
            $table->timestamps();
            $table->index(['professeur_id', 'annee', 'mois']);
        });

        Schema::create('parametres_site', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 80)->unique();
            $table->text('valeur')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_site');
        Schema::dropIfExists('timesheet_signatures');
        Schema::dropIfExists('signature_specimens');
    }
};
