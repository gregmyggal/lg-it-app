<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remplace le CPT lgit_lien. Parent polymorphe : cours | stage | formation | anniversaire.
        Schema::create('classe_liens', function (Blueprint $table) {
            $table->id();
            $table->string('parent_type');
            $table->unsignedBigInteger('parent_id');
            $table->string('titre');
            $table->string('url');
            $table->string('description')->nullable();
            $table->string('theme')->default('outil');
            $table->string('seance')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_liens');
    }
};
