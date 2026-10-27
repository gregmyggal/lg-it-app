<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TS-04 T1c : une signature et un PDF pointent vers la version validée du mois. `version_hash` est copié sur la
 * signature pour survivre à l'anonymisation du payload. Nullable : les mois antérieurs à TS-04 n'ont pas de version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_signatures', function (Blueprint $table) {
            $table->foreignId('version_id')->nullable()->after('mois')->constrained('timesheet_versions')->nullOnDelete();
            $table->char('version_hash', 64)->nullable()->after('version_id');
        });
        Schema::table('timesheet_pdfs', function (Blueprint $table) {
            $table->foreignId('version_id')->nullable()->after('mois')->constrained('timesheet_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_pdfs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_id');
        });
        Schema::table('timesheet_signatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('version_id');
            $table->dropColumn('version_hash');
        });
    }
};
