<?php

namespace App\Http\Requests;

use App\Models\ProfesseurEmployeurMois;
use Illuminate\Foundation\Http\FormRequest;

/** POST /employeurs-mois/lot (staff) : une entité, ou « reprendre le mois précédent ». */
class LotEmployeurMoisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [ProfesseurEmployeurMois::class]);
    }

    public function rules(): array
    {
        return [
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'professeur_ids' => ['required', 'array', 'min:1', 'max:200'],
            'professeur_ids.*' => ['integer', 'distinct', 'exists:professeurs,id'],
            'reprendre_precedent' => ['sometimes', 'boolean'],
            'employeur_id' => ['required_without:reprendre_precedent', 'nullable', 'integer', 'exists:employeurs,id'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
