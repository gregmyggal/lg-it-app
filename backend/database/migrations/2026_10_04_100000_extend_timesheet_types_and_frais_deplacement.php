<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TS-01 T5 : types de lignes alignés sur la fiche de défraiement (animation, cours, préparation, frais de
 * déplacement) — l'ENUM devient une chaîne — et montant forfaitaire d'un frais de déplacement, par année.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->string('type_activite', 30)->default('animation')->change();
        });

        Schema::table('timesheet_parametres', function (Blueprint $table) {
            $table->decimal('frais_deplacement_eur', 8, 2)->default(7.50)->after('plafond_annuel_eur');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_parametres', function (Blueprint $table) {
            $table->dropColumn('frais_deplacement_eur');
        });

        DB::table('timesheets')->whereIn('type_activite', ['cours', 'deplacement'])->update(['type_activite' => 'animation']);
        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('type_activite', ['preparation', 'animation'])->default('animation')->change();
        });
    }
};
