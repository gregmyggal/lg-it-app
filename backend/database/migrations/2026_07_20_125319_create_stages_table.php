<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('slug')->unique();
            $table->string('theme_stage');
            $table->string('tranche_age');
            $table->string('lieu');
            $table->decimal('prix', 8, 2);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('url_logiscool')->nullable();
            // Choix fixes ('Été','Toussaint','Noël','Carnaval','Pâques') — JSON plutôt qu'une table de jointure.
            $table->json('sessions_vacances')->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->enum('statut', ['publish', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
