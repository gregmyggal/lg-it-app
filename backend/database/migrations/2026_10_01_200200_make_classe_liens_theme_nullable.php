<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** CLS-01 T4 : le « type » d'un lien (ancien thème) est facultatif — NULL = aucun type. Les stages/formations/anniversaires gardent « outil ». */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classe_liens', function (Blueprint $table) {
            $table->string('theme')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('classe_liens')->whereNull('theme')->update(['theme' => 'outil']);

        Schema::table('classe_liens', function (Blueprint $table) {
            $table->string('theme')->nullable(false)->default('outil')->change();
        });
    }
};
