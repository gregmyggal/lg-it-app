<?php

namespace App\Http\Requests;

use App\Models\Classe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Seuls le créneau horaire, le lieu et le statut se modifient ici. Changer le cours, la période,
 * le jour ou la date de première séance revient à créer une autre classe (aucune régénération).
 */
class UpdateClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('classe'));
    }

    public function rules(): array
    {
        return [
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'lieu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'statut' => ['sometimes', Rule::in(Classe::STATUTS)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            /** @var Classe $classe */
            $classe = $this->route('classe');
            $debut = $this->input('heure_debut', substr($classe->heure_debut, 0, 5));
            $fin = $this->input('heure_fin', substr($classe->heure_fin, 0, 5));

            if ($fin <= $debut) {
                $v->errors()->add('heure_fin', "L'heure de fin doit être postérieure à l'heure de début.");
            }
        }];
    }
}
