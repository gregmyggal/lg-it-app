<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** POST /classes/{classe}/periodes/{classePeriode}/annuler : motif obligatoire. */
class AnnulerClassePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('classe'));
    }

    public function rules(): array
    {
        return ['motif' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
