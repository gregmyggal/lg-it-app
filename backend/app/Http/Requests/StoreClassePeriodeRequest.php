<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** POST /classes/{classe}/periodes et /periodes/apercu (cours_id facultatif pour l'aperçu). */
class StoreClassePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('classe'));
    }

    public function rules(): array
    {
        $apercu = $this->routeIs('*apercu') || str_ends_with($this->path(), '/apercu');

        return [
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
            'cours_id' => [$apercu ? 'sometimes' : 'required', 'integer', 'exists:cours,id'],
            'date_premiere_session' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
