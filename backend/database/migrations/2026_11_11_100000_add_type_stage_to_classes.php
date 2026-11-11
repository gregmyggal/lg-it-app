<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STG-01 : un stage planifié est une classe de `type = 'stage'` (1 à 5 séances sur une semaine).
 * Il porte une unique ligne `classe_periodes` (qui garde son cours) sans période scolaire : `periode_id` devient nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('classes', 'type')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->string('type', 20)->default('classe')->after('annee_scolaire_id'); // classe | stage
            });
        }

        Schema::table('classe_periodes', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Les stages n'ont pas de période : on les retire avant de rétablir la contrainte.
        $stages = \Illuminate\Support\Facades\DB::table('classes')->where('type', 'stage')->pluck('id');
        if ($stages->isNotEmpty()) {
            $periodes = \Illuminate\Support\Facades\DB::table('classe_periodes')->whereIn('classe_id', $stages)->pluck('id');
            $sessions = \Illuminate\Support\Facades\DB::table('course_sessions')->whereIn('classe_id', $stages)->pluck('id');
            \Illuminate\Support\Facades\DB::table('session_professors')->whereIn('course_session_id', $sessions)->delete();
            \Illuminate\Support\Facades\DB::table('timesheets')->whereIn('course_session_id', $sessions)->update(['course_session_id' => null]);
            \Illuminate\Support\Facades\DB::table('course_sessions')->whereIn('classe_id', $stages)->delete();
            \Illuminate\Support\Facades\DB::table('classe_periodes')->whereIn('id', $periodes)->delete();
            \Illuminate\Support\Facades\DB::table('professeur_classe')->whereIn('classe_id', $stages)->delete();
            \Illuminate\Support\Facades\DB::table('classes')->whereIn('id', $stages)->delete();
        }

        Schema::table('classe_periodes', function (Blueprint $table) {
            $table->unsignedBigInteger('periode_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('classes', 'type')) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
