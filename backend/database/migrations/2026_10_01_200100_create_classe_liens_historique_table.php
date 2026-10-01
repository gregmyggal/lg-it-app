<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-01 T4 (RG-10) : versions des liens — append-only, conservées 6 mois (purge quotidienne). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_liens_historique', function (Blueprint $table) {
            $table->id();
            // Sans FK : la version survit au lien (archivé puis purgé).
            $table->unsignedBigInteger('classe_lien_id')->index();
            $table->string('parent_type');
            $table->unsignedBigInteger('parent_id');
            $table->string('action', 20); // creation|modification|portee|archivage|ordre|restauration
            $table->json('avant')->nullable();
            $table->json('apres')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('restaure_depuis_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['parent_type', 'parent_id', 'created_at'], 'liens_historique_parent_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_liens_historique');
    }
};
