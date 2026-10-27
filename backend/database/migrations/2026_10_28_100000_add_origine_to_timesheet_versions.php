<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TS-05 : origine d'une version (le professeur lui-même, ou la direction par soumission forcée). Non modifiable après création. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_versions', function (Blueprint $table) {
            $table->string('origine', 10)->default('professeur')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_versions', function (Blueprint $table) {
            $table->dropColumn('origine');
        });
    }
};
