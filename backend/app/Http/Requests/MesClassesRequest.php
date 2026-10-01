<?php

namespace App\Http\Requests;

use App\Models\ProfesseurClasse;
use Illuminate\Foundation\Http\FormRequest;

class MesClassesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', ProfesseurClasse::class);
    }

    public function rules(): array
    {
        return ['inclure_terminees' => ['sometimes', 'boolean']];
    }
}
