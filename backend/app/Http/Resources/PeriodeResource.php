<?php

namespace App\Http\Resources;

use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Periode */
class PeriodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'numero' => $this->numero,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
        ];
    }
}
