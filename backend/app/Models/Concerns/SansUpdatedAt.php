<?php

namespace App\Models\Concerns;

/**
 * Pour les modèles avec `UPDATED_AT = null` : Eloquent renvoie alors `null` parmi les colonnes de dates,
 * ce qui lève « Using null as an array offset » (déprécié en PHP 8.5) à la sérialisation.
 */
trait SansUpdatedAt
{
    public function getDates()
    {
        return array_values(array_filter(parent::getDates(), fn ($colonne) => $colonne !== null));
    }
}
