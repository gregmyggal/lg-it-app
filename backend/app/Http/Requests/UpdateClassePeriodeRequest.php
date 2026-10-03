<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** PUT /classes/{classe}/periodes/{classePeriode} : changement de cours. */
class UpdateClassePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('classe'));
    }

    public function rules(): array
    {
        return ['cours_id' => ['required', 'integer', 'exists:cours,id']];
    }
}
