<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STG-01 : un stage planifié se choisit par THÈME (une fiche `stages`), pas par cours.
 * La période de classe d'un stage porte donc `stage_id` et n'a plus de `cours_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classe_periodes', 'stage_id')) {
            Schema::table('classe_periodes', function (Blueprint $table) {
                $table->foreignId('stage_id')->nullable()->after('cours_id')->constrained('stages')->restrictOnDelete();
            });
        }

        Schema::table('classe_periodes', function (Blueprint $table) {
            $table->unsignedBigInteger('cours_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Les stages choisis par thème n'ont pas de cours : on les retire avant de rétablir la contrainte.
        $periodes = DB::table('classe_periodes')->whereNull('cours_id');
        $classes = (clone $periodes)->pluck('classe_id');
        if ($classes->isNotEmpty()) {
            $sessions = DB::table('course_sessions')->whereIn('classe_id', $classes)->pluck('id');
            DB::table('session_professors')->whereIn('course_session_id', $sessions)->delete();
            DB::table('timesheets')->whereIn('course_session_id', $sessions)->update(['course_session_id' => null]);
            DB::table('course_sessions')->whereIn('classe_id', $classes)->delete();
            DB::table('classe_periodes')->whereIn('classe_id', $classes)->delete();
            DB::table('professeur_classe')->whereIn('classe_id', $classes)->delete();
            DB::table('classes')->whereIn('id', $classes)->delete();
        }

        Schema::table('classe_periodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stage_id');
            $table->unsignedBigInteger('cours_id')->nullable(false)->change();
        });
    }
};
