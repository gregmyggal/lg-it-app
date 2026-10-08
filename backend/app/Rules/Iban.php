<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** IBAN valide (structure + clé de contrôle modulo 97), espaces tolérés. */
class Iban implements ValidationRule
{
    public static function normaliser(?string $valeur): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', (string) $valeur));

        return $v === '' ? null : $v;
    }

    /** « BE12 •••• •••• 1234 » : 4 premiers et 4 derniers caractères ; null si vide. */
    public static function masquer(?string $iban): ?string
    {
        $v = self::normaliser($iban);

        return $v === null ? null : substr($v, 0, 4).' •••• •••• '.substr($v, -4);
    }

    public static function estValide(string $iban): bool
    {
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
            return false;
        }
        $rearrange = substr($iban, 4).substr($iban, 0, 4);
        $numerique = '';
        foreach (str_split($rearrange) as $c) {
            $numerique .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }
        $reste = 0;
        foreach (str_split($numerique, 7) as $bloc) {
            $reste = (int) ($reste.$bloc) % 97;
        }

        return $reste === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $iban = self::normaliser(is_string($value) ? $value : '');
        if ($iban !== null && ! self::estValide($iban)) {
            $fail('Le numéro de compte (IBAN) est invalide.');
        }
    }
}
