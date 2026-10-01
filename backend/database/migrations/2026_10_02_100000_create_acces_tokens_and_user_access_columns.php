<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** ADMIN-02 : invitation / réinitialisation du mot de passe par lien à usage unique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invitation_envoyee_le')->nullable()->after('must_change_password');
            $table->timestamp('mot_de_passe_defini_le')->nullable()->after('invitation_envoyee_le');
        });

        // Rattrapage : les comptes déjà autonomes (mot de passe choisi par l'utilisateur).
        DB::table('users')->where('must_change_password', false)->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['mot_de_passe_defini_le' => $user->created_at]);
        });

        Schema::create('acces_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['invitation', 'reinitialisation']);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expire_le');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acces_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_envoyee_le', 'mot_de_passe_defini_le']);
        });
    }
};
