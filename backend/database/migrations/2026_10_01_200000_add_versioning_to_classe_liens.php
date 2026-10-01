<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLS-01 T4 : liens du cours par séance (seance_numero), concurrence optimiste (version), archivage (soft delete),
 * auteurs, et reprise manuelle des anciennes ressources. `seance` (texte libre), `pinned` et `actif` sont conservés
 * mais plus exposés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classe_liens', function (Blueprint $table) {
            $table->unsignedTinyInteger('seance_numero')->nullable()->after('seance');
            $table->unsignedInteger('version')->default(1)->after('seance_numero');
            $table->foreignId('created_by')->nullable()->after('version')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index(['parent_type', 'parent_id', 'seance_numero', 'ordre'], 'classe_liens_portee_ordre_index');
        });

        Schema::table('cours_ressources', function (Blueprint $table) {
            $table->timestamp('repris_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cours_ressources', function (Blueprint $table) {
            $table->dropColumn('repris_at');
        });

        Schema::table('classe_liens', function (Blueprint $table) {
            $table->dropIndex('classe_liens_portee_ordre_index');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['seance_numero', 'version', 'created_by', 'updated_by', 'deleted_at']);
        });
    }
};
