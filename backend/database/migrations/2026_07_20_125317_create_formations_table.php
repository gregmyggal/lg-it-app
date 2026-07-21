<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('slug')->unique();
            $table->longText('programme')->nullable();
            $table->text('extrait')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('format', ['presentiel', 'en_ligne', 'hybride'])->default('presentiel');
            $table->string('duree')->nullable();
            $table->decimal('prix', 8, 2)->nullable();
            $table->string('public_cible')->nullable();
            $table->enum('niveau', ['debutant', 'initie', 'confirme', 'tous'])->nullable();
            $table->date('prochaine_date')->nullable();
            $table->text('objectifs')->nullable();
            $table->string('url_inscription')->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->enum('statut', ['publish', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
