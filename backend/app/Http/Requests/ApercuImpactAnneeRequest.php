<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** POST /annees-scolaires/{annee}/apercu-impact : lecture seule ; les incohérences de dates sortent en `bloquants`. */
class ApercuImpactAnneeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('annee'));
    }

    public function rules(): array
    {
        return [
            'periodes' => ['required', 'array', 'size:2'],
            'periodes.*.numero' => ['required', 'integer', 'in:1,2', 'distinct'],
            'periodes.*.date_debut' => ['required', 'date_format:Y-m-d'],
            'periodes.*.date_fin' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
