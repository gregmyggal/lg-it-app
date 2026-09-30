<?php

namespace App\Http\Resources;

use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * nb_sessions = sessions non annulées (bis inclus), pour coller à la règle de dépassement de 14.
 *
 * @mixin Classe
 */
class ClasseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'cours_id' => $this->cours_id,
            'cours' => $this->whenLoaded('cours', fn () => [
                'id' => $this->cours->id,
                'titre' => $this->cours->titre,
                'slug' => $this->cours->slug,
            ]),
            'annee_scolaire_id' => $this->annee_scolaire_id,
            'annee_scolaire' => $this->whenLoaded('anneeScolaire', fn () => [
                'id' => $this->anneeScolaire->id,
                'libelle' => $this->anneeScolaire->libelle,
            ]),
            'periode_id' => $this->periode_id,
            'periode' => new PeriodeResource($this->whenLoaded('periode')),
            'jour_semaine' => $this->jour_semaine,
            'heure_debut' => substr($this->heure_debut, 0, 5),
            'heure_fin' => substr($this->heure_fin, 0, 5),
            'lieu' => $this->lieu,
            'date_premiere_session' => $this->date_premiere_session->toDateString(),
            'statut' => $this->statut,
            'nb_sessions' => $this->whenCounted('sessionsActives'),
            'prochaine_session' => $this->when(
                $this->relationLoaded('prochaineSession'),
                fn () => $this->prochaineSession ? [
                    'id' => $this->prochaineSession->id,
                    'seance_numero' => $this->prochaineSession->seance_numero,
                    'bis_rang' => $this->prochaineSession->bis_rang,
                    'libelle' => $this->prochaineSession->libelle(),
                    'date' => $this->prochaineSession->date->toDateString(),
                ] : null
            ),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
