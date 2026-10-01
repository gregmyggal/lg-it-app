<?php

namespace App\Http\Requests;

use App\Models\ClasseLien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Modification d'un lien de cours (T4) : `version` obligatoire (concurrence optimiste, 409 si dépassée). */
class UpdateCoursLienRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'titre' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url:http,https', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', Rule::in(ClasseLien::TYPES)],
            'seance_numero' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.ClasseLien::NB_SEANCES],
        ];
    }

    public function attributes(): array
    {
        return ['titre' => 'titre', 'url' => 'adresse', 'description' => 'description', 'type' => 'type', 'seance_numero' => 'séance', 'version' => 'version'];
    }
}
