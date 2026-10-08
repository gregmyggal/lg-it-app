<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * RGPD-01 : (dé)chiffrement en masse de professeurs.compte_bancaire, indépendamment du modèle
 * (lecture brute via DB::table) : sert à la commande Artisan et à la migration (up/down).
 */
class ChiffrementIban
{
    public static function estChiffre(string $brut): bool
    {
        try {
            Crypt::decryptString($brut);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Chiffre les valeurs encore en clair. Idempotent : une valeur déjà chiffrée n'est pas retouchée.
     *
     * @return array{chiffres: int, deja_chiffres: int, vides: int}
     */
    public static function chiffrerTout(bool $simulation = false): array
    {
        $r = ['chiffres' => 0, 'deja_chiffres' => 0, 'vides' => 0];

        DB::table('professeurs')->select('id', 'compte_bancaire')->orderBy('id')->chunkById(200, function ($lignes) use (&$r, $simulation) {
            foreach ($lignes as $l) {
                if ($l->compte_bancaire === null || trim($l->compte_bancaire) === '') {
                    $r['vides']++;
                } elseif (self::estChiffre($l->compte_bancaire)) {
                    $r['deja_chiffres']++;
                } else {
                    $r['chiffres']++;
                    if (! $simulation) {
                        DB::table('professeurs')->where('id', $l->id)->update(['compte_bancaire' => Crypt::encryptString($l->compte_bancaire)]);
                    }
                }
            }
        });

        return $r;
    }

    /** Opération inverse (rollback de migration) : remet les IBAN en clair. */
    public static function dechiffrerTout(): int
    {
        $n = 0;

        DB::table('professeurs')->select('id', 'compte_bancaire')->orderBy('id')->chunkById(200, function ($lignes) use (&$n) {
            foreach ($lignes as $l) {
                if ($l->compte_bancaire !== null && self::estChiffre($l->compte_bancaire)) {
                    DB::table('professeurs')->where('id', $l->id)->update(['compte_bancaire' => Crypt::decryptString($l->compte_bancaire)]);
                    $n++;
                }
            }
        });

        return $n;
    }
}
