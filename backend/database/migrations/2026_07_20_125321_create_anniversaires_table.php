<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anniversaires', function (Blueprint $table) {
            $table->id();
            $table->string('nom_theme');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('tranche_age');
            $table->decimal('tarif', 8, 2);
            $table->string('image_path')->nullable();
            $table->string('url_logiscool')->nullable();
            $table->text('inclus')->nullable();
            $table->text('options')->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->enum('statut', ['publish', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anniversaires');
    }
};
