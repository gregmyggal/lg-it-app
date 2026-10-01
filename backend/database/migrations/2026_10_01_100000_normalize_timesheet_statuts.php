<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CLS-01 T3 : `statut_validation` n'accepte que brouillon/soumis/valide alors que le code écrit « confirmé » et
 * « généré » (refusés par MySQL). On remplace l'ENUM par une chaîne et on normalise sans accent :
 * brouillon → soumis → confirme → genere.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE timesheets MODIFY statut_validation VARCHAR(20) NOT NULL DEFAULT 'brouillon'");

        DB::table('timesheets')->whereIn('statut_validation', ['valide', 'confirmé'])->update(['statut_validation' => 'confirme']);
        DB::table('timesheets')->where('statut_validation', 'généré')->update(['statut_validation' => 'genere']);
    }

    /** Rétablit l'ENUM d'origine ; les statuts hors enum (confirme, genere) sont ramenés à « valide ». */
    public function down(): void
    {
        DB::table('timesheets')->whereIn('statut_validation', ['confirme', 'genere'])->update(['statut_validation' => 'valide']);

        DB::statement("ALTER TABLE timesheets MODIFY statut_validation ENUM('brouillon','soumis','valide') NOT NULL DEFAULT 'brouillon'");
    }
};
