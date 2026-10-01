<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemplacerProfesseurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('replace', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'professeur_remplace_id' => ['required', 'integer', 'exists:professeurs,id'],
            'professeur_remplacant_id' => ['required', 'integer', 'exists:professeurs,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'professeur_remplace_id' => 'professeur remplacé',
            'professeur_remplacant_id' => 'remplaçant',
        ];
    }
}
