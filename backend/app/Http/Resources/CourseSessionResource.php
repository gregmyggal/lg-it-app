<?php

namespace App\Http\Resources;

use App\Models\CourseSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CourseSession */
class CourseSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $staff = (bool) $user?->can('update', $this->resource);

        return [
            'id' => $this->id,
            'classe_id' => $this->classe_id,
            'classe' => $this->whenLoaded('classe', fn () => [
                'id' => $this->classe->id,
                'cours_id' => $this->classe->cours_id,
                'cours' => $this->classe->relationLoaded('cours') ? [
                    'id' => $this->classe->cours->id,
                    'titre' => $this->classe->cours->titre,
                ] : null,
                'jour_semaine' => $this->classe->jour_semaine,
                'periode_id' => $this->classe->periode_id,
            ]),
            'seance_numero' => $this->seance_numero,
            'bis_rang' => $this->bis_rang,
            'libelle' => $this->libelle(),
            'remplace_session_id' => $this->remplace_session_id,
            'date' => $this->date->toDateString(),
            'heure_debut' => substr($this->heure_debut, 0, 5),
            'heure_fin' => substr($this->heure_fin, 0, 5),
            'lieu' => $this->lieu,
            'statut' => $this->statut,
            'motif_annulation' => $this->motif_annulation,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'alerte_calendrier' => $this->alerte_calendrier,
            'can' => [
                'update' => $staff && $this->isMovable(),
                'cancel' => (bool) $user?->can('cancel', $this->resource) && $this->isCancellable(),
                'bis' => $staff,
            ],
        ];
    }
}
