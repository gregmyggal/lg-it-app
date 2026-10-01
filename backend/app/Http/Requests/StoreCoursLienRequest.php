<?php

namespace App\Http\Requests;

use App\Models\ClasseLien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création d'un lien de cours (T4) : l'autorisation est faite par le contrôleur (Gate sur le cours). */
class StoreCoursLienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(ClasseLien::TYPES)],
            'seance_numero' => ['nullable', 'integer', 'min:1', 'max:'.ClasseLien::NB_SEANCES],
        ];
    }

    public function attributes(): array
    {
        return ['titre' => 'titre', 'url' => 'adresse', 'description' => 'description', 'type' => 'type', 'seance_numero' => 'séance'];
    }
}
