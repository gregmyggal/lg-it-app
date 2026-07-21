<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remplace le repeater ACF "dates" (sessions) du stage.
        Schema::create('stage_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->date('date_session');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_dates');
    }
};
