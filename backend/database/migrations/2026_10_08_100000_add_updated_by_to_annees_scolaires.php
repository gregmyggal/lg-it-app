<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLS-03 : auteur de la dernière modification d'une année scolaire (verrou optimiste, liste). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('statut')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
