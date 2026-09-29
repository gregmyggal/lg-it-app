<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_calendar_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Configuration
            $table->string('nom', 255);
            $table->enum('vue_defaut', ['month', 'week', 'agenda'])->default('month');
            $table->json('filtres')->nullable();

            // Audit
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->unique(['user_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_calendar_views');
    }
};
