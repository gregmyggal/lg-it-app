<?php

namespace App\Http\Requests;

use App\Models\Employeur;
use App\Rules\Iban;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /employeurs (admin). */
class StoreEmployeurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employeur::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rpm' => self::normaliserRpm($this->input('rpm')),
            'compte_bancaire' => Iban::normaliser($this->input('compte_bancaire')),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('employeurs', 'code')],
            'nom' => ['required', 'string', 'max:255'],
            'rpm' => ['required', 'string', 'regex:/^BE\d{4}\.\d{3}\.\d{3}$/'],
            'compte_bancaire' => ['required', 'string', new Iban],
            'adresse' => ['required', 'string', 'max:255'],
            'par_defaut' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
            'couleur_badge' => ['sometimes', 'nullable', Rule::in(['bleu', 'violet', 'vert', 'orange', 'gris'])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Le code est en minuscules, chiffres et _ (ex. mon_entite).',
            'rpm.regex' => 'Le numéro RPM doit être au format BE0123.456.789.',
        ];
    }

    /** « be 0770 479 710 », « BE0770479710 » → « BE0770.479.710 » ; autre valeur laissée telle quelle (rejetée par la règle). */
    public static function normaliserRpm(mixed $v): mixed
    {
        if (! is_string($v)) {
            return $v;
        }
        $c = strtoupper(preg_replace('/[\s.]/', '', $v));

        return preg_match('/^BE\d{10}$/', $c) ? substr($c, 0, 6).'.'.substr($c, 6, 3).'.'.substr($c, 9, 3) : trim($v);
    }
}
