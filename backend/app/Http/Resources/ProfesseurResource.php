<?php

namespace App\Http\Resources;

use App\Rules\Iban;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RGPD-01 : représentation d'un professeur. L'IBAN (masqué dans le modèle via $hidden) n'est ajouté qu'à la
 * demande ($avecIban : fiche détail pour le staff ou le titulaire) ; partout ailleurs, seuls le masque et
 * le booléen « renseigné » sont renvoyés. Forme plate (sans enveloppe `data`), comme avant.
 *
 * @mixin \App\Models\Professeur
 */
class ProfesseurResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, private readonly bool $avecIban = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $iban = $this->resource->compte_bancaire;

        $data = $this->resource->toArray() + [
            'compte_bancaire_renseigne' => filled($iban),
            'compte_bancaire_masque' => Iban::masquer($iban),
        ];

        if ($this->avecIban) {
            $data['compte_bancaire'] = $iban;
        }

        return $data;
    }
}
