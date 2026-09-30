<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'motif_annulation' => ['required', 'string', 'max:1000'],
        ];
    }
}
