<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->date('date_prestation');
            $table->decimal('nombre_heures', 4, 2);
            $table->foreignId('cours_id')->nullable()->constrained('cours')->nullOnDelete();
            $table->text('commentaire')->nullable();
            $table->enum('statut_validation', ['brouillon', 'soumis', 'valide'])->default('brouillon');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
