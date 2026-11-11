<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** DEP-01 : lot de fichiers ; type, taille et quota sont contrôlés fichier par fichier dans DepotService. */
class StoreDepotFichiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorisation (stage + droits) dans le contrôleur.
    }

    public function rules(): array
    {
        $classe = $this->route('classe');

        return [
            'fichiers' => ['required', 'array', 'min:1', 'max:20'],
            'fichiers.*' => ['file'],
            'libelle' => ['nullable', 'string', 'max:60'],
            'course_session_id' => ['nullable', 'integer', Rule::exists('course_sessions', 'id')->where('classe_id', $classe?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'fichiers.required' => 'Choisissez au moins un fichier.',
            'fichiers.max' => 'Déposez 20 fichiers au maximum à la fois.',
            'fichiers.*.file' => 'Un des fichiers n\'a pas pu être lu. Réessayez.',
            'libelle.max' => 'Le libellé ne peut pas dépasser 60 caractères.',
            'course_session_id.exists' => 'Cette séance n\'appartient pas à ce stage.',
        ];
    }
}
