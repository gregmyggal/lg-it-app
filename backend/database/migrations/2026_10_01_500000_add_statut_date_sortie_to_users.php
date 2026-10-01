<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ADMIN-01 : gestion des comptes admin/directeur — statut et date de sortie. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('statut', ['actif', 'inactif'])->default('actif')->after('role');
            $table->date('date_sortie')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['statut', 'date_sortie']);
        });
    }
};
