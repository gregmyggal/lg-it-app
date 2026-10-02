<?php

namespace App\Http\Resources;

use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Services\TarifResolver;
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
                'adapt' => (bool) $user?->can('adapt', $this->resource),
            ],
        ];
    }

    private function libelleClasse($session): ?string
    {
        $classe = $session->classe;
        $titre = $classe->relationLoaded('cours') ? $classe->cours?->titre : null;

        return $titre ? $titre.' — séance '.$session->seance_numero : null;
    }

    /** Montant brut de la saisie (heures × tarif en vigueur, ou nombre × forfait de déplacement), nul si aucun tarif. */
    private function montant(Request $request): ?float
    {
        // Mémo limité à la requête (jamais statique : les identifiants se réutilisent d'un test à l'autre).
        $memo = $request->attributes->get('unites_montant', []);
        $cle = $this->professeur_id.'|'.$this->date_prestation->toDateString().'|'.$this->type_activite;
        if (! array_key_exists($cle, $memo)) {
            $resolver = app(TarifResolver::class);
            $memo[$cle] = $resolver->unite($this->resource, ProfesseurTarif::where('professeur_id', $this->professeur_id)->get());
            $request->attributes->set('unites_montant', $memo);
        }

        return $memo[$cle] === null ? null : round((float) $this->nombre_heures * $memo[$cle], 2);
    }
}
