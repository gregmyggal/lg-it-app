<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();

            // Récurrence parameters
            $table->enum('type', ['weekly', 'biweekly', 'monthly'])->default('weekly');
            $table->string('jours_semaine', 50)->nullable(); // "1,3,5" (lundi, mercredi, vendredi)
            $table->date('date_debut');
            $table->date('date_fin')->nullable();

            // Horaires par défaut pour les sessions générées
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->string('lieu_defaut', 255)->nullable();

            // Statut
            $table->enum('statut', ['active', 'paused', 'archived'])->default('active');

            // Audit
            $table->timestamps();

            // Indexes
            $table->index(['cours_id', 'statut']);
            $table->index(['date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_recurrences');
    }
};
