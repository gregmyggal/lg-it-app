<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'lieu' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $session = $this->route('session');
            $debut = $this->input('heure_debut', substr($session->heure_debut, 0, 5));
            $fin = $this->input('heure_fin', substr($session->heure_fin, 0, 5));

            if ($fin <= $debut) {
                $v->errors()->add('heure_fin', "L'heure de fin doit être postérieure à l'heure de début.");
            }
        }];
    }
}
