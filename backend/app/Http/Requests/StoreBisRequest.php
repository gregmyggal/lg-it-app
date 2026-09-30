<?php

namespace App\Http\Requests;

use App\Services\ClasseSessionGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('classe'));
    }

    public function rules(): array
    {
        return [
            'seance_numero' => ['required', 'integer', 'between:1,'.ClasseSessionGenerator::NB_SEANCES],
            'date' => ['required', 'date_format:Y-m-d'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'confirmer_depassement' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $classe = $this->route('classe');
            $debut = $this->input('heure_debut') ?: substr($classe->heure_debut, 0, 5);
            $fin = $this->input('heure_fin') ?: substr($classe->heure_fin, 0, 5);

            if ($fin <= $debut) {
                $v->errors()->add('heure_fin', "L'heure de fin doit être postérieure à l'heure de début.");
            }
        }];
    }
}
