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
        Schema::table('anniversaires', function (Blueprint $table) {
            $table->text('section_apropos')->nullable();
            $table->text('section_deroulement')->nullable()->comment('Déroulé de la fête (JSON array avec timing)');
            $table->text('section_pourquoi')->nullable()->comment('Pourquoi nos anniversaires ? (JSON array)');
            $table->text('sidebar_tarification')->nullable()->comment('Tarification (JSON object)');
            $table->text('sidebar_inclus')->nullable()->comment('Ce qui est inclus (JSON array)');
            $table->text('sidebar_options')->nullable()->comment('Options disponibles (text)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anniversaires', function (Blueprint $table) {
            $table->dropColumn(['section_apropos', 'section_deroulement', 'section_pourquoi', 'sidebar_tarification', 'sidebar_inclus', 'sidebar_options']);
        });
    }
};
