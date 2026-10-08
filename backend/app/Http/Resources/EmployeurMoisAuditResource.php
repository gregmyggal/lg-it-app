<?php

namespace App\Http\Resources;

use App\Models\EmployeurMoisAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** EMP-01 (RG-9) : une ligne du journal des changements d'employeur. @mixin EmployeurMoisAudit */
class EmployeurMoisAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'annee' => $this->annee,
            'mois' => $this->mois,
            'employeur_avant' => $this->avant ? ['id' => $this->avant->id, 'nom' => $this->avant->nom] : null,
            'employeur_apres' => ['id' => $this->apres->id, 'nom' => $this->apres->nom],
            'motif' => $this->motif,
            'auteur' => $this->auteur?->name ?? 'Système',
            'created_at' => $this->created_at,
        ];
    }
}
