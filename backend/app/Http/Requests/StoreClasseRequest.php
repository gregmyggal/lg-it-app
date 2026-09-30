<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;

/** Utilisée pour POST /classes et POST /classes/apercu (même payload). */
class StoreClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Classe::class);
    }

    public function rules(): array
    {
        return [
            'cours_id' => ['required', 'integer', 'exists:cours,id'],
            'annee_scolaire_id' => ['required', 'integer', 'exists:annees_scolaires,id'],
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
            'jour_semaine' => ['required', 'integer', 'between:1,7'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'date_premiere_session' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
