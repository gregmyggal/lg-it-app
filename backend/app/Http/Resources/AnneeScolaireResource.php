<?php

namespace App\Http\Resources;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnneeScolaire */
class AnneeScolaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'statut' => $this->statut,
            'periodes' => PeriodeResource::collection($this->whenLoaded('periodes')),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'import_fwb' => (bool) $user?->can('importFwb', CalendrierScolaire::class),
            ],
        ];
    }
}
