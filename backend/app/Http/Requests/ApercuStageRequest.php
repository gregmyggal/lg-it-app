<?php

namespace App\Http\Requests;

use App\Models\Classe;
use App\Services\StageSessionGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** STG-01 : aperçu d'un stage (le thème est facultatif tant que le formulaire n'est pas complet). */
class ApercuStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Classe::class);
    }

    public function rules(): array
    {
        $creneau = ['sometimes', 'array'];

        return [
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'stage_id' => [$this->themeRequis() ? 'required' : 'sometimes', 'integer', 'exists:stages,id'],
            'lieu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'demi_journee_defaut' => ['sometimes', Rule::in(StageSessionGenerator::DEMI_JOURNEES)],
            'creneaux' => $creneau,
            'creneaux.matin' => $creneau,
            'creneaux.apres_midi' => $creneau,
            'creneaux.*.heure_debut' => ['sometimes', 'date_format:H:i'],
            'creneaux.*.heure_fin' => ['sometimes', 'date_format:H:i'],
            'jours' => ['sometimes', 'array', 'max:'.StageSessionGenerator::NB_JOURS_MAX],
            'jours.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'jours.*.demi_journee' => ['required', Rule::in(StageSessionGenerator::DEMI_JOURNEES)],
        ];
    }

    protected function themeRequis(): bool
    {
        return false;
    }

    public function after(): array
    {
        return [function (Validator $v) {
            foreach (StageSessionGenerator::DEMI_JOURNEES as $demi) {
                $defaut = StageSessionGenerator::CRENEAUX_DEFAUT[$demi];
                $debut = $this->input("creneaux.{$demi}.heure_debut", $defaut['heure_debut']);
                $fin = $this->input("creneaux.{$demi}.heure_fin", $defaut['heure_fin']);
                if ($fin <= $debut) {
                    $v->errors()->add("creneaux.{$demi}.heure_fin", "L'heure de fin doit être postérieure à l'heure de début.");
                }
            }

            if ($v->errors()->has('date_debut') || $v->errors()->has('jours.*')) {
                return;
            }
            $lundi = Carbon::parse($this->input('date_debut'))->startOfWeek();
            foreach ((array) $this->input('jours', []) as $i => $jour) {
                if (empty($jour['date'])) {
                    continue;
                }
                $d = Carbon::parse($jour['date']);
                if ($d->lt($lundi) || $d->gt($lundi->copy()->addDays(StageSessionGenerator::NB_JOURS_MAX - 1))) {
                    $v->errors()->add("jours.{$i}.date", 'Ce jour est hors de la semaine du stage (lundi → vendredi).');
                }
            }
        }];
    }
}
