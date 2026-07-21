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
        Schema::table('cours', function (Blueprint $table) {
            $table->text('section_apropos')->nullable()->comment('Contenu: À propos de ce cours');
            $table->text('section_apprendras')->nullable()->comment('Contenu: Ce que tu apprendras (JSON array)');
            $table->text('section_format')->nullable()->comment('Contenu: Format et horaires (JSON object)');
            $table->text('section_pourqui')->nullable()->comment('Contenu: Pour qui ? (texte + JSON array)');
            $table->text('sidebar_pratiques')->nullable()->comment('Sidebar: Infos pratiques (JSON object)');
            $table->text('sidebar_benefits')->nullable()->comment('Sidebar: Pourquoi ce cours ? (JSON array)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn(['section_apropos', 'section_apprendras', 'section_format', 'section_pourqui', 'sidebar_pratiques', 'sidebar_benefits']);
        });
    }
};
