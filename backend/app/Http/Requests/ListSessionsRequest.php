<?php

namespace App\Http\Requests;

use App\Models\CourseSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', CourseSession::class);
    }

    public function rules(): array
    {
        return [
            'classe_id' => ['sometimes', 'integer'],
            'cours_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'statut' => ['sometimes', Rule::in(CourseSession::STATUTS)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
