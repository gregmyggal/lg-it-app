<?php

namespace App\Http\Resources;

use App\Models\Employeur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** EMP-01 : entité employeur pour le staff (IBAN en clair, matrice §3) + capacités. @mixin Employeur */
class EmployeurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'nom' => $this->nom,
            'rpm' => $this->rpm,
            'compte_bancaire' => $this->compteFormate(),
            'adresse' => $this->adresse,
            'par_defaut' => $this->par_defaut,
            'actif' => $this->actif,
            'couleur_badge' => $this->couleur_badge,
            'coordonnees_completes' => $this->coordonneesCompletes(),
            'mois_lies' => $this->whenCounted('moisLies'),
            'updated_at' => $this->updated_at,
            'can' => ['update' => $request->user()->can('update', $this->resource)],
        ];
    }
}
