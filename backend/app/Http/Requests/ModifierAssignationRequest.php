<?php

namespace App\Http\Requests;

use App\Models\Classe;
use App\Models\ProfesseurClasse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModifierAssignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageProfesseurs', Classe::class);
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::in(ProfesseurClasse::ROLES)],
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
        ];
    }

    public function attributes(): array
    {
        return ['date_debut' => 'date de début', 'date_fin' => 'date de fin', 'role' => 'rôle'];
    }
}
