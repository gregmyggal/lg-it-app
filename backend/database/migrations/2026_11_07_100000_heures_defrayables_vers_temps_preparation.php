<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEF-02 : « heures défrayables » (valeur fixe par séance, préparation incluse) devient « temps de préparation » :
 * séance normale = durée de la séance + préparation ; séance bis = durée seule. Les anciennes valeurs sont converties
 * en retirant la durée de séance par défaut de la même ligne (2 h − 1,5 h = 0,5 h), plancher à 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_parametres', function (Blueprint $table) {
            $table->renameColumn('heures_defrayables', 'temps_preparation');
        });
        DB::table('timesheet_parametres')->update(['temps_preparation' => DB::raw('GREATEST(temps_preparation - duree_seance_defaut, 0)')]);

        Schema::table('timesheet_parametres_historique', function (Blueprint $table) {
            $table->renameColumn('heures_defrayables_avant', 'temps_preparation_avant');
            $table->renameColumn('heures_defrayables_apres', 'temps_preparation_apres');
        });
        DB::table('timesheet_parametres_historique')->whereNotNull('temps_preparation_avant')->update([
            'temps_preparation_avant' => DB::raw('GREATEST(temps_preparation_avant - COALESCE(duree_seance_avant, 1.5), 0)'),
            'temps_preparation_apres' => DB::raw('GREATEST(temps_preparation_apres - COALESCE(duree_seance_apres, 1.5), 0)'),
        ]);

        Schema::table('cours', function (Blueprint $table) {
            $table->renameColumn('heures_defrayables', 'temps_preparation');
        });
        DB::table('cours')->whereNotNull('temps_preparation')->update(['temps_preparation' => DB::raw('GREATEST(temps_preparation - 1.5, 0)')]);
    }

    public function down(): void
    {
        DB::table('cours')->whereNotNull('temps_preparation')->update(['temps_preparation' => DB::raw('temps_preparation + 1.5')]);
        Schema::table('cours', fn (Blueprint $t) => $t->renameColumn('temps_preparation', 'heures_defrayables'));

        DB::table('timesheet_parametres_historique')->whereNotNull('temps_preparation_avant')->update([
            'temps_preparation_avant' => DB::raw('temps_preparation_avant + COALESCE(duree_seance_avant, 1.5)'),
            'temps_preparation_apres' => DB::raw('temps_preparation_apres + COALESCE(duree_seance_apres, 1.5)'),
        ]);
        Schema::table('timesheet_parametres_historique', function (Blueprint $table) {
            $table->renameColumn('temps_preparation_avant', 'heures_defrayables_avant');
            $table->renameColumn('temps_preparation_apres', 'heures_defrayables_apres');
        });

        DB::table('timesheet_parametres')->update(['temps_preparation' => DB::raw('temps_preparation + duree_seance_defaut')]);
        Schema::table('timesheet_parametres', fn (Blueprint $t) => $t->renameColumn('temps_preparation', 'heures_defrayables'));
    }
};
