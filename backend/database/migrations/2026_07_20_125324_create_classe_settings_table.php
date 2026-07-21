<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réglages du portail /classe par activité (séance active, code d'accès).
        Schema::create('classe_settings', function (Blueprint $table) {
            $table->id();
            $table->string('parent_type');
            $table->unsignedBigInteger('parent_id');
            $table->string('seance_active')->nullable();
            $table->string('access_code', 12)->nullable();
            $table->timestamps();

            $table->unique(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_settings');
    }
};
