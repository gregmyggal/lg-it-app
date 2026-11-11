<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnvoyerDepotLienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorisation (stage + droits) dans le contrôleur.
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email:rfc', 'max:190']];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Saisissez l\'adresse e-mail du parent.',
            'email.email' => 'Cette adresse e-mail n\'est pas valide.',
        ];
    }
}
