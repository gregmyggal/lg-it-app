<?php

namespace App\Http\Requests;

use App\Models\ProfesseurClasse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjouterProfesseurSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('replace', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'role' => ['sometimes', Rule::in(ProfesseurClasse::ROLES)],
        ];
    }

    public function attributes(): array
    {
        return ['professeur_id' => 'professeur', 'role' => 'rôle'];
    }
}
