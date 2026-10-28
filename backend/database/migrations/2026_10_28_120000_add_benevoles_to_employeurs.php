<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une entité employeur emploie soit des bénévoles (volontaires défrayés), soit des employés. Le vocabulaire des fiches PDF,
 * de la signature et de la vérification publique en dépend. Défaut « bénévoles » : le comportement actuel est conservé
 * tant que l'admin n'a pas changé le paramètre d'une entité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employeurs', function (Blueprint $table) {
            $table->boolean('benevoles')->default(true)->after('actif');
        });
        // L-IT Solutions emploie des employés (décision du PO) ; l'ASBL garde les bénévoles.
        DB::table('employeurs')->where('code', 'lit_solutions')->update(['benevoles' => false]);
    }

    public function down(): void
    {
        Schema::table('employeurs', function (Blueprint $table) {
            $table->dropColumn('benevoles');
        });
    }
};
