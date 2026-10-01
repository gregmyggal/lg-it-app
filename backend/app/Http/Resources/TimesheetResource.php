<?php

namespace App\Http\Resources;

use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Saisie d'heures. Forme historique conservée (liste = tableau, pas d'enveloppe `data`) + `session`, `montant_brut`
 * (le montant de la saisie du propriétaire ; Q22 : un professeur voit ses propres euros) et `can`.
 *
 * @mixin Timesheet
 */
class TimesheetResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $session = $this->relationLoaded('session') ? $this->session : null;

        return $this->resource->attributesToArray() + [
            'professeur' => $this->whenLoaded('professeur'),
            'cours' => $this->whenLoaded('cours'),
            'session' => $session ? [
                'id' => $session->id,
                'libelle' => $session->libelle(),
                'date' => $session->date->toDateString(),
                'classe_id' => $session->classe_id,
                'classe_libelle' => $session->relationLoaded('classe') ? $this->libelleClasse($session) : null,
            ] : null,
            'montant_brut' => $this->montant($request),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'submit' => (bool) $user?->can('submit', $this->resource),
                'validate' => (bool) $user?->can('validateEntry', $this->resource),
            ],
        ];
    }

    private function libelleClasse($session): ?string
    {
        $classe = $session->classe;
        $titre = $classe->relationLoaded('cours') ? $classe->cours?->titre : null;

        return $titre ? $titre.' — séance '.$session->seance_numero : null;
    }

    /** Montant brut de la saisie (heures × tarif en vigueur à la date), nul si aucun tarif. */
    private function montant(Request $request): ?float
    {
        // Mémo limité à la requête (jamais statique : les identifiants se réutilisent d'un test à l'autre).
        $memo = $request->attributes->get('tarifs_horaires', []);
        $cle = $this->professeur_id.'|'.$this->date_prestation->toDateString();
        if (! array_key_exists($cle, $memo)) {
            $tarif = ProfesseurTarif::effectiveAt($this->professeur_id, $this->date_prestation);
            $memo[$cle] = $tarif ? (float) $tarif->tarif_horaire_eur : null;
            $request->attributes->set('tarifs_horaires', $memo);
        }

        return $memo[$cle] === null ? null : round((float) $this->nombre_heures * $memo[$cle], 2);
    }
}
