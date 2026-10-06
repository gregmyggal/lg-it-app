<?php

namespace App\Http\Resources;

use App\Models\CourseSession;
use App\Services\TimesheetService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Le cours d'une session est celui de sa période de classe (CLS-02) : `classePeriode.cours` et
 * `classePeriode.periode` doivent être chargés pour renseigner cours, periode_numero, libelle_complet et hors_periode.
 *
 * @mixin CourseSession
 */
class CourseSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $staff = (bool) $user?->can('update', $this->resource);
        $cp = $this->relationLoaded('classePeriode') ? $this->classePeriode : null;
        $periodeCharge = $cp?->relationLoaded('periode') ?? false;
        $cours = $cp?->relationLoaded('cours') ? $cp->cours : null;

        return [
            'id' => $this->id,
            'classe_id' => $this->classe_id,
            'classe_periode_id' => $this->classe_periode_id,
            'periode_numero' => $periodeCharge ? $cp->periode->numero : null,
            'classe' => $this->whenLoaded('classe', fn () => [
                'id' => $this->classe->id,
                'jour_semaine' => $this->classe->jour_semaine,
            ]),
            'cours' => $cours ? ['id' => $cours->id, 'titre' => $cours->titre] : null,
            'periode_id' => $cp?->periode_id,
            'seance_numero' => $this->seance_numero,
            'bis_rang' => $this->bis_rang,
            'libelle' => $this->libelle(),
            'libelle_complet' => $this->libelleComplet(),
            'hors_periode' => $periodeCharge ? $this->isHorsPeriode() : false,
            'avertissements' => $periodeCharge ? $this->avertissements() : [],
            'remplace_session_id' => $this->remplace_session_id,
            'date' => $this->date->toDateString(),
            'heure_debut' => substr($this->heure_debut, 0, 5),
            'heure_fin' => substr($this->heure_fin, 0, 5),
            'lieu' => $this->lieu,
            'heures_defrayables' => app(TimesheetService::class)->heuresDefrayables($this->resource),
            'statut' => $this->statut,
            'motif_annulation' => $this->motif_annulation,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'alerte_calendrier' => $this->alerte_calendrier,
            'professeurs' => $this->whenLoaded('sessionProfesseurs', fn () => $this->sessionProfesseurs
                ->filter(fn ($l) => $l->relationLoaded('professeur'))
                ->map(fn ($l) => ProfesseurClasseResource::professeurLeger($l->professeur) + [
                    'role' => $l->role,
                    'origine' => $l->origine,
                    'remplace' => $l->remplace,
                    'remplacant_a_trouver' => $l->remplace && $l->remplace_par_professeur_id === null,
                ])->values()),
            'can' => [
                'update' => $staff && $this->isMovable(),
                'cancel' => (bool) $user?->can('cancel', $this->resource) && $this->isCancellable(),
                'bis' => $staff && ! $this->isAnnulee(),
            ],
        ];
    }
}
