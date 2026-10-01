<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;

class TerminerAssignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageProfesseurs', Classe::class);
    }

    public function rules(): array
    {
        return ['date_fin' => ['sometimes', 'nullable', 'date_format:Y-m-d']];
    }

    public function attributes(): array
    {
        return ['date_fin' => 'date de fin'];
    }
}
