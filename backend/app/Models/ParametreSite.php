<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Paramètres généraux du site (clé → valeur), hors paramètres annuels des timesheets. */
class ParametreSite extends Model
{
    public const SIGNATURE_VERIFICATION_PUBLIQUE = 'signature_verification_publique';

    protected $table = 'parametres_site';

    protected $fillable = ['cle', 'valeur', 'updated_by'];

    public static function booleen(string $cle, bool $defaut = false): bool
    {
        $valeur = static::where('cle', $cle)->value('valeur');

        return $valeur === null ? $defaut : $valeur === '1';
    }

    public static function definir(string $cle, bool|string $valeur, User $auteur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => is_bool($valeur) ? ($valeur ? '1' : '0') : $valeur, 'updated_by' => $auteur->id]);
    }
}
