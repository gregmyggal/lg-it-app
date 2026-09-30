<?php

namespace App\Http\Resources;

use App\Models\CalendrierScolaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CalendrierScolaire */
class CalendrierScolaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'type' => $this->type,
            'libelle' => $this->libelle,
            'source' => $this->source,
            'masque' => $this->masque,
            'modifie_manuellement' => $this->modifie_manuellement,
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
