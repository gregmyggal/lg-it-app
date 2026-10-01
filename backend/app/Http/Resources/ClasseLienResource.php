<?php

namespace App\Http\Resources;

use App\Models\ClasseLien;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lien d'un cours (CLS-01 T4). `type` = ancien `theme` ; `pinned` et `actif` ne sont pas exposés.
 *
 * @mixin ClasseLien
 */
class ClasseLienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'url' => $this->url,
            'description' => $this->description,
            'type' => $this->theme,
            'seance_numero' => $this->seance_numero,
            'hors_programme' => $this->estHorsProgramme(),
            'ordre' => $this->ordre,
            'version' => $this->version,
            'modifie_par' => $this->whenLoaded('auteurModification', fn () => $this->auteurModification
                ? ['id' => $this->auteurModification->id, 'nom' => $this->auteurModification->name] : null),
            'modifie_le' => $this->updated_at?->toIso8601String(),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }

    /** Forme publique (élève, code de partage) : aucun auteur, aucune version, aucun droit. */
    public static function publique(ClasseLien $lien): array
    {
        return [
            'id' => $lien->id,
            'titre' => $lien->titre,
            'url' => $lien->url,
            'description' => $lien->description,
            'type' => $lien->theme,
            'seance_numero' => $lien->seance_numero,
        ];
    }
}
