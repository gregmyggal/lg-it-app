<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Ajouter le type d'activité après nombre_heures
            if (!Schema::hasColumn('timesheets', 'type_activite')) {
                $table->enum('type_activite', ['preparation', 'animation'])->default('animation')->after('nombre_heures');
            }
        });

        // SQLite ne supporte pas ALTER TABLE MODIFY; utiliser le schéma builder
        if (!Schema::hasColumn('timesheets', 'lissage_applique')) {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->boolean('lissage_applique')->default(false)->after('commentaire');
            });
        }

        if (!Schema::hasColumn('timesheets', 'signature_professeur')) {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->timestamp('signature_professeur')->nullable()->after('validated_at');
            });
        }

        if (!Schema::hasColumn('timesheets', 'pdf_generated_at')) {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->timestamp('pdf_generated_at')->nullable()->after('signature_professeur');
            });
        }

        if (!Schema::hasColumn('timesheets', 'pdf_generated_by')) {
            Schema::table('timesheets', function (Blueprint $table) {
                $table->foreignId('pdf_generated_by')->nullable()->constrained('users')->nullOnDelete()->after('pdf_generated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $columns = ['type_activite', 'lissage_applique', 'signature_professeur', 'pdf_generated_at', 'pdf_generated_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('timesheets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
