<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Durée en heures décimales multiple de 15 minutes (0,25 h). */
class PasDeQuinzeMinutes implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_numeric($value) && fmod(round((float) $value * 100), 25) != 0) {
            $fail('La valeur doit être un multiple de 15 minutes (0,25 h).');
        }
    }
}
