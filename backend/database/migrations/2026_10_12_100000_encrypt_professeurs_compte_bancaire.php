<?php

use App\Support\ChiffrementIban;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RGPD-01 : l'IBAN des professeurs est chiffré au repos (cast Eloquent « encrypted », APP_KEY).
 * Le chiffré dépasse 34 caractères : colonne en text. SAUVEGARDER la base ET APP_KEY avant de migrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professeurs', function (Blueprint $table) {
            $table->text('compte_bancaire')->nullable()->change();
        });

        ChiffrementIban::chiffrerTout();
    }

    public function down(): void
    {
        ChiffrementIban::dechiffrerTout();

        Schema::table('professeurs', function (Blueprint $table) {
            $table->string('compte_bancaire', 34)->nullable()->change();
        });
    }
};
