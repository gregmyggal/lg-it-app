<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/** Règles de cohérence année scolaire ⇄ périodes (RG-3). */
trait ValidePeriodes
{
    /** @return array<string, mixed> */
    protected function reglesPeriodes(bool $requis): array
    {
        $presence = $requis ? 'required' : 'sometimes';

        return [
            'periodes' => [$presence, 'array', 'size:2'],
            'periodes.*.numero' => ['required', 'integer', 'in:1,2', 'distinct'],
            'periodes.*.date_debut' => ['required', 'date_format:Y-m-d'],
            'periodes.*.date_fin' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @param  string  $debutAnnee  date de début effective de l'année (Y-m-d)
     * @param  string  $finAnnee  date de fin effective de l'année (Y-m-d)
     */
    protected function verifierPeriodes(Validator $validator, ?string $debutAnnee, ?string $finAnnee): void
    {
        $periodes = collect($this->input('periodes', []))->keyBy('numero');

        if ($validator->errors()->isNotEmpty() || $periodes->count() !== 2 || ! $periodes->has(1) || ! $periodes->has(2)) {
            return;
        }

        foreach ($periodes as $numero => $p) {
            if ($p['date_fin'] < $p['date_debut']) {
                $validator->errors()->add('periodes', "La fin de la période {$numero} doit être postérieure à son début.");
            }
            if ($debutAnnee && $finAnnee && ($p['date_debut'] < $debutAnnee || $p['date_fin'] > $finAnnee)) {
                $validator->errors()->add('periodes', "La période {$numero} doit être comprise dans l'année scolaire.");
            }
        }

        if ($periodes[2]['date_debut'] <= $periodes[1]['date_fin']) {
            $validator->errors()->add('periodes', 'La période 2 doit commencer après la fin de la période 1.');
        }
    }
}
