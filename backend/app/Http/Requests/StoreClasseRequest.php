<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;

/** Utilisée pour POST /classes et POST /classes/apercu (même payload) : 1 ou 2 périodes (CLS-02). */
class StoreClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Classe::class);
    }

    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['required', 'integer', 'exists:annees_scolaires,id'],
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'periodes' => ['required', 'array', 'min:1', 'max:2'],
            'periodes.*.periode_id' => ['required', 'integer', 'distinct', 'exists:periodes,id'],
            'periodes.*.cours_id' => ['required', 'integer', 'exists:cours,id'],
            'periodes.*.date_premiere_session' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
