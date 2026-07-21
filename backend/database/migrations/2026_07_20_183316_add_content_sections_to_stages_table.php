<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->text('section_apropos')->nullable();
            $table->text('section_programme')->nullable()->comment('Programme jour par jour (JSON array)');
            $table->text('section_strengths')->nullable()->comment('Points forts (JSON array)');
            $table->text('sidebar_infos')->nullable()->comment('Infos pratiques (JSON object)');
            $table->text('sidebar_inclus')->nullable()->comment('Ce qui est inclus (JSON array)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropColumn(['section_apropos', 'section_programme', 'section_strengths', 'sidebar_infos', 'sidebar_inclus']);
        });
    }
};
