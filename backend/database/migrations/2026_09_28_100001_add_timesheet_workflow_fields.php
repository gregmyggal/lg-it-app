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
            $table->enum('type_activite', ['preparation', 'animation'])->default('animation')->after('nombre_heures');
        });

        // Changer l'enum statut_validation → statut_workflow (3 états → 4 états)
        // Migration des données: valide → généré (signifie "traité par directeur")
        DB::statement("UPDATE timesheets SET statut_validation = 'généré' WHERE statut_validation = 'valide'");

        // Modifier le type de colonne pour le nouvel enum
        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('statut_validation', ['brouillon', 'soumis', 'confirmé', 'généré'])->change();
        });

        // Ajouter les champs pour lissage et signature
        Schema::table('timesheets', function (Blueprint $table) {
            $table->boolean('lissage_applique')->default(false)->after('commentaire');
            $table->timestamp('signature_professeur')->nullable()->after('validated_at');
            $table->timestamp('pdf_generated_at')->nullable()->after('signature_professeur');
            $table->foreignId('pdf_generated_by')->nullable()->constrained('users')->nullOnDelete()->after('pdf_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn([
                'type_activite',
                'lissage_applique',
                'signature_professeur',
                'pdf_generated_at',
                'pdf_generated_by',
            ]);
        });

        // Remigrer les données: généré → valide
        DB::statement("UPDATE timesheets SET statut_validation = 'valide' WHERE statut_validation = 'généré'");

        // Restaurer l'enum original
        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('statut_validation', ['brouillon', 'soumis', 'valide'])->change();
        });
    }
};
