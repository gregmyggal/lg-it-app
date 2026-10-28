<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TS-06 : notes internes (direction et admin uniquement) sur un mois ou sur une saisie. Table SÉPARÉE de `timesheets` :
 * la ressource d'une saisie expose tous ses attributs, une colonne y fuiterait vers le professeur.
 * `cle` garantit une seule note par mois et une seule par saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('mois');
            $table->foreignId('timesheet_id')->nullable()->constrained('timesheets')->cascadeOnDelete();
            $table->string('cle', 40)->unique();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texte');
            $table->timestamps();

            $table->index(['professeur_id', 'annee', 'mois']);
            $table->index(['annee', 'mois']);
        });

        Schema::create('timesheet_note_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('timesheet_notes')->cascadeOnDelete();
            $table->text('texte'); // version remplacée
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete(); // qui a remplacé cette version
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_note_historique');
        Schema::dropIfExists('timesheet_notes');
    }
};
