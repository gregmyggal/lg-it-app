<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** TS-00 : plafonds de défraiement par année civile (modifiables) et IBAN du professeur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_parametres', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->decimal('plafond_journalier_eur', 8, 2);
            $table->decimal('plafond_annuel_eur', 10, 2);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('timesheet_parametres_historique', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee');
            $table->decimal('plafond_journalier_avant', 8, 2)->nullable();
            $table->decimal('plafond_journalier_apres', 8, 2);
            $table->decimal('plafond_annuel_avant', 10, 2)->nullable();
            $table->decimal('plafond_annuel_apres', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('annee');
        });

        // Valeurs jusqu'ici codées en dur (44,02 €/jour, 1 760,83 €/an) pour l'année en cours.
        DB::table('timesheet_parametres')->insert([
            'annee' => (int) date('Y'),
            'plafond_journalier_eur' => 44.02,
            'plafond_annuel_eur' => 1760.83,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('professeurs', function (Blueprint $table) {
            $table->string('compte_bancaire', 34)->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('professeurs', function (Blueprint $table) {
            $table->dropColumn('compte_bancaire');
        });
        Schema::dropIfExists('timesheet_parametres_historique');
        Schema::dropIfExists('timesheet_parametres');
    }
};
