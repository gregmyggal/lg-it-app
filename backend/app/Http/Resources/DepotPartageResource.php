<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DepotPartage */
class DepotPartageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'statut' => $this->statut(),
            'expire_le' => $this->expire_le->toIso8601String(),
            'revoque_le' => $this->revoque_le?->toIso8601String(),
            'nb_fichiers' => $this->fichiers_count ?? $this->fichiers()->count(),
            'nb_ouvertures' => $this->nb_ouvertures,
            'derniere_ouverture_le' => $this->derniere_ouverture_le?->toIso8601String(),
            'cree_par' => $this->createur?->name,
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
