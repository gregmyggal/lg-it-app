<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TS-01 T5 : fiches de défraiement PDF générées (une version par génération ; un déverrouillage admin en crée une nouvelle). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('version');
            $table->string('chemin');
            $table->decimal('total_eur', 10, 2);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->unique(['professeur_id', 'annee', 'mois', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_pdfs');
    }
};
