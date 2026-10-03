<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-02 RG-5b : traçabilité des changements de cours d'une période de classe. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_periode_cours_historique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_periode_id')->constrained('classe_periodes')->cascadeOnDelete();
            $table->foreignId('ancien_cours_id')->constrained('cours')->restrictOnDelete();
            $table->foreignId('nouveau_cours_id')->constrained('cours')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index('classe_periode_id', 'cp_cours_hist_periode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_periode_cours_historique');
    }
};
