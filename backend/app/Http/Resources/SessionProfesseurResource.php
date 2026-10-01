<?php

namespace App\Http\Resources;

use App\Models\SessionProfesseur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessionProfesseur */
class SessionProfesseurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_session_id' => $this->course_session_id,
            'professeur_id' => $this->professeur_id,
            'professeur' => $this->whenLoaded('professeur', fn () => ProfesseurClasseResource::professeurLeger($this->professeur)),
            'role' => $this->role,
            'origine' => $this->origine,
            'remplace' => $this->remplace,
            'remplace_par_professeur_id' => $this->remplace_par_professeur_id,
            'remplace_par' => $this->whenLoaded('remplacePar', fn () => $this->remplacePar
                ? ProfesseurClasseResource::professeurLeger($this->remplacePar) : null),
        ];
    }
}
