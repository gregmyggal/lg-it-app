<?php

namespace App\Http\Resources;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Services\AnneeScolaireService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AnneeScolaire */
class AnneeScolaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $classes = (int) ($this->classes_count ?? $this->classes()->count());
        $sessions = (int) ($this->sessions_count ?? ($classes > 0 ? $this->sessions()->count() : 0));
        $calendrier = (int) ($this->calendrier_count ?? $this->calendrier()->actives()->count());
        $aujourdhui = now('Europe/Brussels')->toDateString();

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'date_debut' => $this->date_debut->toDateString(),
            'date_fin' => $this->date_fin->toDateString(),
            'statut' => $this->statut,
            'periodes' => PeriodeResource::collection($this->whenLoaded('periodes')),
            'classes_count' => $classes,
            'sessions_count' => $sessions,
            'calendrier_count' => $calendrier,
            'en_cours' => $this->date_debut->toDateString() <= $aujourdhui && $aujourdhui <= $this->date_fin->toDateString(),
            // `version` à renvoyer à la modification (verrou optimiste).
            'updated_at' => $this->updated_at?->copy()->setTimezone('Europe/Brussels')->toIso8601String(),
            'updated_by' => $this->updatedBy ? ['id' => $this->updatedBy->id, 'name' => $this->updatedBy->name] : null,
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => $classes === 0 && (bool) $user?->can('delete', $this->resource),
                'archiver' => (bool) $user?->can('archiver', $this->resource),
                'import_fwb' => (bool) $user?->can('importFwb', CalendrierScolaire::class),
                'raison_non_supprimable' => AnneeScolaireService::raisonNonSupprimable($classes, $sessions),
            ],
        ];
    }
}
