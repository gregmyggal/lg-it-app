<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidePeriodes;
use App\Models\AnneeScolaire;
use App\Services\AnneePeriodesRegles;
use App\Services\AnneeScolaireService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /annees-scolaires/{annee} (CLS-03). `version` (= updated_at lu) est obligatoire dès que libellé,
 * dates ou périodes sont envoyés ; une année archivée refuse tout changement de dates (409) avant validation.
 */
class UpdateAnneeScolaireRequest extends FormRequest
{
    use ValidePeriodes;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('annee'));
    }

    protected function prepareForValidation(): void
    {
        app(AnneeScolaireService::class)->refuserSiArchivee($this->route('annee'), $this->all());
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
            'version' => ['required_with:libelle,date_debut,date_fin,periodes', 'string', 'max:40'],
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

            $periodes = collect($this->input('periodes', []))->keyBy('numero');
            if ($periodes->count() === 2 && $periodes->has(1) && $periodes->has(2)) {
                // Sans dates d'année explicites, l'année suit P1 début → P2 fin (RG-1) : pas de contrôle d'inclusion.
                $explicites = $this->has('date_debut') || $this->has('date_fin');
                $this->verifierPeriodes($v, $debut, $fin, $annee->id, $explicites);
            } elseif ($v->errors()->isEmpty() && ($this->has('date_debut') || $this->has('date_fin'))) {
                if ($message = app(AnneePeriodesRegles::class)->chevauchement($debut, $fin, $annee->id)) {
                    $v->errors()->add('periodes', $message);
                }
            }
        }];
    }
}
