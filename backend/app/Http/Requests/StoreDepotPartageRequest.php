<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepotPartageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorisation (stage + droits) dans le contrôleur.
    }

    public function rules(): array
    {
        return [
            'fichier_ids' => ['required', 'array', 'min:1', 'max:200'],
            'fichier_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'fichier_ids.required' => 'Choisissez au moins un fichier à partager.',
            'fichier_ids.min' => 'Choisissez au moins un fichier à partager.',
        ];
    }
}
