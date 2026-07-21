<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remplace le repeater ACF "ressources" du cours.
        Schema::create('cours_ressources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->string('titre_ressource');
            $table->string('url_ressource');
            $table->enum('type_ressource', ['video', 'outil', 'document', 'jeu'])->default('outil');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours_ressources');
    }
};
