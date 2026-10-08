<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** RGPD-01 : la migration chiffre l'existant (up) et le restitue en clair (down). Sans RefreshDatabase (DDL). */
class ChiffrementIbanMigrationTest extends TestCase
{
    private const MIGRATION = '2026_10_12_100000_encrypt_professeurs_compte_bancaire';

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh');
        parent::tearDown();
    }

    private function colonne(): array
    {
        return collect(Schema::getColumns('professeurs'))->firstWhere('name', 'compte_bancaire');
    }

    public function test_up_chiffre_les_iban_existants_et_down_les_restitue_en_clair(): void
    {
        Artisan::call('migrate:fresh');
        $steps = DB::table('migrations')->where('migration', '>=', self::MIGRATION)->count();
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]), Artisan::output());
        $this->assertSame('varchar', $this->colonne()['type_name']);

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'A', 'email' => 'a@t.test', 'password' => 'x', 'role' => 'professeur', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'B', 'email' => 'b@t.test', 'password' => 'x', 'role' => 'professeur', 'created_at' => now(), 'updated_at' => now()],
        ]);
        foreach ([1 => 'BE68539007547034', 2 => null] as $id => $iban) {
            DB::table('professeurs')->insert(['id' => $id, 'user_id' => $id, 'prenom' => 'A', 'nom' => 'B', 'email' => "$id@t.test", 'statut' => 'actif', 'date_entree' => '2026-01-01', 'compte_bancaire' => $iban, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertSame('text', $this->colonne()['type_name']);
        $brut = DB::table('professeurs')->where('id', 1)->value('compte_bancaire');
        $this->assertStringNotContainsString('BE68', $brut);
        $this->assertSame('BE68539007547034', Crypt::decryptString($brut));
        $this->assertNull(DB::table('professeurs')->where('id', 2)->value('compte_bancaire'));

        // down() : retour au clair, colonne d'origine.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => $steps]), Artisan::output());
        $this->assertSame('BE68539007547034', DB::table('professeurs')->where('id', 1)->value('compte_bancaire'));
        $this->assertSame('varchar', $this->colonne()['type_name']);

        // Et on peut rejouer up().
        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());
        $this->assertStringNotContainsString('BE68', DB::table('professeurs')->where('id', 1)->value('compte_bancaire'));
    }
}
