<?php

namespace App\Http\Requests\Concerns;

use App\Services\AnneePeriodesRegles;
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
     * @param  ?string  $debutAnnee  début de l'année à contrôler (null = déduit de P1)
     * @param  ?string  $finAnnee  fin de l'année à contrôler (null = déduite de P2)
     * @param  bool  $bornesExplicites  les périodes doivent être comprises dans $debutAnnee/$finAnnee
     */
    protected function verifierPeriodes(Validator $validator, ?string $debutAnnee, ?string $finAnnee, ?int $ignoreAnneeId = null, bool $bornesExplicites = true): void
    {
        $periodes = collect($this->input('periodes', []))->keyBy('numero');

        if ($validator->errors()->isNotEmpty() || $periodes->count() !== 2 || ! $periodes->has(1) || ! $periodes->has(2)) {
            return;
        }

        $messages = app(AnneePeriodesRegles::class)->bloquants(
            $periodes->values()->all(),
            $ignoreAnneeId,
            $bornesExplicites ? $debutAnnee : null,
            $bornesExplicites ? $finAnnee : null,
        );
        foreach ($messages as $message) {
            $validator->errors()->add('periodes', $message);
        }
    }
}
