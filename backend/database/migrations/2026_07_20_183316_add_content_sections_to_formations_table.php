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
        Schema::table('formations', function (Blueprint $table) {
            $table->text('section_apropos')->nullable();
            $table->text('section_competences')->nullable()->comment('Compétences développées (JSON array)');
            $table->text('section_approche')->nullable()->comment('Notre approche (JSON array)');
            $table->text('section_parcours')->nullable()->comment('Votre parcours en phases (JSON array)');
            $table->text('sidebar_infos')->nullable()->comment('Infos clés (JSON object)');
            $table->text('sidebar_public')->nullable()->comment('Pour qui ? (JSON array)');
            $table->text('sidebar_resultats')->nullable()->comment('Résultats attendus (JSON array)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formations', function (Blueprint $table) {
            $table->dropColumn(['section_apropos', 'section_competences', 'section_approche', 'section_parcours', 'sidebar_infos', 'sidebar_public', 'sidebar_resultats']);
        });
    }
};
