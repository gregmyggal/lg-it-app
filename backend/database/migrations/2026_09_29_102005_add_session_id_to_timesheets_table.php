<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreignId('course_session_id')->nullable()->after('cours_id')->constrained('course_sessions')->nullOnDelete();
            $table->index(['professeur_id', 'course_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeignIdFor('course_sessions');
            $table->dropColumn('course_session_id');
        });
    }
};
