<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClassesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Classe::class);
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['sometimes', 'integer'],
            'periode_id' => ['sometimes', 'integer'],
            'cours_id' => ['sometimes', 'integer'],
            'jour_semaine' => ['sometimes', 'integer', 'between:1,7'],
            'statut' => ['sometimes', Rule::in(Classe::STATUTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
