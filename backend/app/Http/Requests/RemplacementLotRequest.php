<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** UI-M6 : remplacer un professeur par un autre sur plusieurs séances (aperçu et application). */
class RemplacementLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'session_ids' => ['required', 'array', 'min:1', 'max:60'],
            'session_ids.*' => ['integer', 'distinct', 'exists:course_sessions,id'],
            'remplacant_id' => ['required', 'integer', Rule::exists('professeurs', 'id')->where(fn ($q) => $q->where('statut', '!=', 'inactif'))],
        ];
    }

    public function attributes(): array
    {
        return [
            'session_ids' => 'séances',
            'remplacant_id' => 'remplaçant',
        ];
    }
}
