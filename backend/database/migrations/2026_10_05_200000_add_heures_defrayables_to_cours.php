<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DEF-01 T2 : heures défrayables propres à un cours (NULL = défaut global de l'année). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->decimal('heures_defrayables', 4, 2)->nullable()->after('menu_order');
        });
    }

    public function down(): void
    {
        Schema::table('cours', function (Blueprint $table) {
            $table->dropColumn('heures_defrayables');
        });
    }
};
