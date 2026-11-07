<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TS-07 : saisie ajoutée par la direction/admin au nom d'un professeur (NULL = encodée par le professeur lui-même). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreignId('ajoute_par_user_id')->nullable()->after('commentaire')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ajoute_par_user_id');
        });
    }
};
