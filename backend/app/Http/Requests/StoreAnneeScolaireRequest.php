<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidePeriodes;
use App\Models\AnneeScolaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnneeScolaireRequest extends FormRequest
{
    use ValidePeriodes;

    public function authorize(): bool
    {
        return $this->user()->can('create', AnneeScolaire::class);
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:20', 'unique:annees_scolaires,libelle'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after:date_debut'],
            'statut' => ['sometimes', Rule::in(AnneeScolaire::STATUTS)],
        ] + $this->reglesPeriodes(requis: true);
    }

    public function after(): array
    {
        return [fn (Validator $v) => $this->verifierPeriodes($v, $this->input('date_debut'), $this->input('date_fin'))];
    }
}
