<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T3 : `statut_validation` n'accepte que brouillon/soumis/valide alors que le code écrit « confirmé » et
 * « généré » (refusés par MySQL). On remplace l'ENUM par une chaîne et on normalise sans accent :
 * brouillon → soumis → confirme → genere.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema plutôt que du SQL brut : respecte le préfixe de tables (DB_PREFIX, base OVH partagée).
        Schema::table('timesheets', function (Blueprint $table) {
            $table->string('statut_validation', 20)->default('brouillon')->change();
        });

        DB::table('timesheets')->whereIn('statut_validation', ['valide', 'confirmé'])->update(['statut_validation' => 'confirme']);
        DB::table('timesheets')->where('statut_validation', 'généré')->update(['statut_validation' => 'genere']);
    }

    /** Rétablit l'ENUM d'origine ; les statuts hors enum (confirme, genere) sont ramenés à « valide ». */
    public function down(): void
    {
        DB::table('timesheets')->whereIn('statut_validation', ['confirme', 'genere'])->update(['statut_validation' => 'valide']);

        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('statut_validation', ['brouillon', 'soumis', 'valide'])->default('brouillon')->change();
        });
    }
};
