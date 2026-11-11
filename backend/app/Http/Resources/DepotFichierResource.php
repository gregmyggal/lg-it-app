<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DepotFichier */
class DepotFichierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->espace?->session;

        return [
            'id' => $this->id,
            'nom' => $this->nom_original,
            'libelle' => $this->libelle,
            'taille' => $this->taille,
            'mime' => $this->mime,
            // Espace du fichier : « stage » (stage entier) ou « seance ».
            'portee' => $this->espace?->course_session_id ? 'seance' : 'stage',
            'course_session_id' => $this->espace?->course_session_id,
            'seance_numero' => $session?->seance_numero,
            'depose_par' => $this->deposePar?->name,
            'depose_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
