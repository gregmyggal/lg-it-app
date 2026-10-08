<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** RGPD-01 : trace append-only (qui a lu l'IBAN / téléchargé des fiches de qui). Jamais modifiée ni supprimée à l'unité. */
class AccesDonneeSensible extends Model
{
    public const UPDATED_AT = null;

    public const LECTURE_IBAN = 'lecture_iban';

    public const TELECHARGEMENT_PDF = 'telechargement_pdf';

    public const EXPORT_ZIP = 'export_zip';

    /** Durée de conservation du journal, en mois. */
    public const RETENTION_MOIS = 12;

    protected $table = 'acces_donnees_sensibles';

    protected $fillable = ['user_id', 'professeur_id', 'action'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'accès est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'accès est en ajout seul.'));
    }
}
