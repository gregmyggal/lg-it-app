<?php

namespace App\Http\Requests;

use App\Models\CalendrierScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCalendrierEntreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('entree'));
    }

    public function rules(): array
    {
        return [
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::in(CalendrierScolaire::TYPES)],
            'libelle' => ['sometimes', 'string', 'max:255'],
            // Permet de « démasquer » une entrée FWB supprimée.
            'masque' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            /** @var CalendrierScolaire $entree */
            $entree = $this->route('entree');
            $debut = $this->input('date_debut', $entree->date_debut->toDateString());
            $fin = $this->input('date_fin', $entree->date_fin->toDateString());

            if ($fin < $debut) {
                $v->errors()->add('date_fin', 'La date de fin doit être postérieure ou égale à la date de début.');
            }
        }];
    }
}
