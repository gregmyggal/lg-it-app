<?php

namespace App\Http\Requests;

/** STG-01 : création d'un stage ; mêmes champs que l'aperçu, avec cours et au moins un jour. */
class StoreStageRequest extends ApercuStageRequest
{
    protected function coursRequis(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), ['jours' => ['required', 'array', 'min:1', 'max:5']]);
    }
}
