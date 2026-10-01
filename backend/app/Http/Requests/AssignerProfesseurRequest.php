<?php

namespace App\Http\Requests;

use App\Models\Classe;
use App\Models\ProfesseurClasse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aperçu et assignation, depuis la classe (professeur_id) ou depuis le professeur (classe_id) :
 * même payload, même service.
 */
class AssignerProfesseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageProfesseurs', Classe::class);
    }

    public function rules(): array
    {
        $depuisClasse = $this->route('classe') !== null;

        return [
            'professeur_id' => [$depuisClasse ? 'required' : 'prohibited', 'integer', 'exists:professeurs,id'],
            'classe_id' => [$depuisClasse ? 'prohibited' : 'required', 'integer', 'exists:classes,id'],
            'role' => ['sometimes', Rule::in(ProfesseurClasse::ROLES)],
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
        ];
    }

    public function attributes(): array
    {
        return [
            'professeur_id' => 'professeur',
            'classe_id' => 'classe',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'role' => 'rôle',
        ];
    }
}
