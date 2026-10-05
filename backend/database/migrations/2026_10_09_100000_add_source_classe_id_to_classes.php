<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-07 : classe source d'une copie (traçabilité « Dupliquée de… », aucune synchronisation). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('classes', 'source_classe_id')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('source_classe_id')->nullable()->after('statut')->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_classe_id');
        });
    }
};
