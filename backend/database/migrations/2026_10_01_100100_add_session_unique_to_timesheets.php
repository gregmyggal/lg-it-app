<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T3 (R-T3-1) : une seule saisie par (professeur, session, type d'activité). Les saisies libres
 * (course_session_id NULL) ne sont pas contraintes : MySQL tolère plusieurs NULL dans un index unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->unique(['professeur_id', 'course_session_id', 'type_activite'], 'timesheets_prof_session_type_unique');
            $table->index(['professeur_id', 'date_prestation'], 'timesheets_prof_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropUnique('timesheets_prof_session_type_unique');
            $table->dropIndex('timesheets_prof_date_index');
        });
    }
};
