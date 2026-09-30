<?php

namespace App\Http\Requests;

use App\Models\CalendrierScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCalendrierEntreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CalendrierScolaire::class);
    }

    public function rules(): array
    {
        return [
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'type' => ['required', Rule::in(CalendrierScolaire::TYPES)],
            'libelle' => ['required', 'string', 'max:255'],
        ];
    }
}
