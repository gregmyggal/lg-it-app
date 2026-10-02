<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TS-01 T1 : historique des adaptations d'une saisie par le staff (avant/après + motif). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professeur_id')->constrained('professeurs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('avant');
            $table->json('apres');
            $table->text('motif');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['professeur_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_audits');
    }
};
