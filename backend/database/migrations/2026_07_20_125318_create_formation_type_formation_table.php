<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_type_formation', function (Blueprint $table) {
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->foreignId('type_formation_id')->constrained('types_formation')->cascadeOnDelete();
            $table->primary(['formation_id', 'type_formation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_type_formation');
    }
};
