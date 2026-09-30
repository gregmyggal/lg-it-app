<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidePeriodes;
use App\Models\AnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAnneeScolaireRequest extends FormRequest
{
    use ValidePeriodes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('annee'));
    }

    public function rules(): array
    {
        /** @var AnneeScolaire $annee */
        $annee = $this->route('annee');

        return [
            'libelle' => ['sometimes', 'string', 'max:20', Rule::unique('annees_scolaires', 'libelle')->ignore($annee->id)],
            'date_debut' => ['sometimes', 'date_format:Y-m-d'],
            'date_fin' => ['sometimes', 'date_format:Y-m-d'],
            'statut' => ['sometimes', Rule::in(AnneeScolaire::STATUTS)],
        ] + $this->reglesPeriodes(requis: false);
    }

    public function after(): array
    {
        return [function (Validator $v) {
            /** @var AnneeScolaire $annee */
            $annee = $this->route('annee');
            $debut = $this->input('date_debut', $annee->date_debut->toDateString());
            $fin = $this->input('date_fin', $annee->date_fin->toDateString());

            if ($fin <= $debut) {
                $v->errors()->add('date_fin', 'La date de fin doit être postérieure à la date de début.');
            }
            $this->verifierPeriodes($v, $debut, $fin);
        }];
    }
}
