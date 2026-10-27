<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TS-04 (ADR-0002) : instantanés immuables d'un mois de timesheet, soumis par le professeur ou validés par la
 * direction. `lignes` est un texte canonique (jamais une colonne JSON : MySQL en réordonnerait les clés et le hash
 * ne se retrouverait plus). `actif_cle` garantit une seule version active par (professeur, mois, type).
 * La suppression forcée d'un professeur (PROF-02) emporte ses versions, comme ses saisies et ses signatures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->string('type', 10); // soumise | validee
            $table->unsignedSmallInteger('numero');
            $table->string('statut', 10)->default('active'); // active | remplacee
            $table->string('actif_cle', 40)->nullable()->unique();
            $table->unsignedTinyInteger('format')->default(1);
            $table->longText('lignes')->nullable(); // texte canonique, nul après purge (T3)
            $table->decimal('total_heures', 8, 2);
            $table->decimal('total_eur', 10, 2);
            $table->char('content_hash', 64);
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('purge_at')->nullable();

            $table->unique(['professeur_id', 'annee', 'mois', 'type', 'numero'], 'ts_versions_numero_unique');
            $table->index(['annee', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_versions');
    }
};
