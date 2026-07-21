<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cours_type_cours', function (Blueprint $table) {
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('type_cours_id')->constrained('types_cours')->cascadeOnDelete();
            $table->primary(['cours_id', 'type_cours_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cours_type_cours');
    }
};
