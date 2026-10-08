<?php

use App\Services\EmployeurBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EMP-01 : employeur d'un animateur, mois par mois (ASBL ou L-IT Solutions). Entités, employeur du mois,
 * journal des changements, et employeur figé sur chaque fiche PDF. Reprise de l'historique = ASBL (idempotente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employeurs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom');
            $table->string('rpm', 30);
            $table->text('compte_bancaire')->nullable(); // chiffré (cast Eloquent « encrypted »)
            $table->string('adresse');
            $table->boolean('par_defaut')->default(false);
            $table->boolean('actif')->default(true);
            $table->string('couleur_badge', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('professeur_employeurs_mois', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->foreignId('employeur_id')->constrained('employeurs')->restrictOnDelete();
            $table->string('source', 12)->default('explicite'); // explicite | migration | fige
            $table->unsignedInteger('version')->default(1); // verrou optimiste (409 si périmé)
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['professeur_id', 'annee', 'mois']);
        });

        Schema::create('employeur_mois_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->foreignId('employeur_avant_id')->nullable()->constrained('employeurs')->restrictOnDelete();
            $table->foreignId('employeur_apres_id')->constrained('employeurs')->restrictOnDelete();
            $table->text('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['professeur_id', 'annee', 'mois']);
        });

        Schema::table('timesheet_pdfs', function (Blueprint $table) {
            $table->foreignId('employeur_id')->nullable()->constrained('employeurs')->restrictOnDelete();
            $table->text('employeur_snapshot')->nullable(); // JSON chiffré : nom, rpm, compte, adresse au moment de la génération
        });

        app(EmployeurBackfillService::class)->executer();
    }

    public function down(): void
    {
        Schema::table('timesheet_pdfs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employeur_id');
            $table->dropColumn('employeur_snapshot');
        });
        Schema::dropIfExists('employeur_mois_audits');
        Schema::dropIfExists('professeur_employeurs_mois');
        Schema::dropIfExists('employeurs');
    }
};
