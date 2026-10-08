<?php

namespace App\Http\Requests;

use App\Rules\Iban;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PUT /employeurs/{employeur} (admin). Le code ne change pas. */
class UpdateEmployeurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employeur'));
    }

    protected function prepareForValidation(): void
    {
        $fusion = [];
        if ($this->has('rpm')) {
            $fusion['rpm'] = StoreEmployeurRequest::normaliserRpm($this->input('rpm'));
        }
        if ($this->has('compte_bancaire')) {
            $fusion['compte_bancaire'] = Iban::normaliser($this->input('compte_bancaire'));
        }
        $this->merge($fusion);
    }

    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'rpm' => ['sometimes', 'required', 'string', 'regex:/^BE\d{4}\.\d{3}\.\d{3}$/'],
            'compte_bancaire' => ['sometimes', 'nullable', 'string', new Iban],
            'adresse' => ['sometimes', 'required', 'string', 'max:255'],
            'par_defaut' => ['sometimes', 'boolean'],
            'actif' => ['sometimes', 'boolean'],
            'couleur_badge' => ['sometimes', 'nullable', Rule::in(['bleu', 'violet', 'vert', 'orange', 'gris'])],
        ];
    }

    public function messages(): array
    {
        return ['rpm.regex' => 'Le numéro RPM doit être au format BE0123.456.789.'];
    }
}
