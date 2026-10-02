<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DEF-01 T1 : heures défrayables par séance et durée de séance par défaut (par année civile, historisées). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_parametres', function (Blueprint $table) {
            $table->decimal('heures_defrayables', 4, 2)->default(2.00)->after('frais_deplacement_eur');
            $table->decimal('duree_seance_defaut', 4, 2)->default(1.50)->after('heures_defrayables');
        });

        Schema::table('timesheet_parametres_historique', function (Blueprint $table) {
            $table->decimal('heures_defrayables_avant', 4, 2)->nullable();
            $table->decimal('heures_defrayables_apres', 4, 2)->nullable();
            $table->decimal('duree_seance_avant', 4, 2)->nullable();
            $table->decimal('duree_seance_apres', 4, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_parametres_historique', function (Blueprint $table) {
            $table->dropColumn(['heures_defrayables_avant', 'heures_defrayables_apres', 'duree_seance_avant', 'duree_seance_apres']);
        });

        Schema::table('timesheet_parametres', function (Blueprint $table) {
            $table->dropColumn(['heures_defrayables', 'duree_seance_defaut']);
        });
    }
};
