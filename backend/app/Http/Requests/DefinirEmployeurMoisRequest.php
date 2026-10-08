<?php

namespace App\Http\Requests;

use App\Models\ProfesseurEmployeurMois;
use Illuminate\Foundation\Http\FormRequest;

/** PUT /professeurs/{professeur}/employeurs-mois/{annee}/{mois} (staff). */
class DefinirEmployeurMoisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', [ProfesseurEmployeurMois::class, $this->route('professeur')]);
    }

    public function rules(): array
    {
        return [
            'employeur_id' => ['required', 'integer', 'exists:employeurs,id'],
            'motif' => ['nullable', 'string', 'max:1000'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }
}
