<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * TS-04 : instantané immuable d'un mois (soumis par le professeur, ou validé par la direction). Seul le statut peut
 * passer de « active » à « remplacée » ; la purge de rétention (T3) est l'exception documentée dans l'ADR-0002.
 */
class TimesheetVersion extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_SOUMISE = 'soumise';

    public const TYPE_VALIDEE = 'validee';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_REMPLACEE = 'remplacee';

    public const FORMAT = 1;

    /** Seuls champs modifiables après création. */
    private const MUTABLES = ['statut', 'actif_cle'];

    protected $fillable = [
        'professeur_id', 'annee', 'mois', 'type', 'numero', 'statut', 'actif_cle', 'format', 'lignes',
        'total_heures', 'total_eur', 'content_hash', 'auteur_id',
    ];

    protected $casts = [
        'annee' => 'integer',
        'mois' => 'integer',
        'numero' => 'integer',
        'format' => 'integer',
        'total_heures' => 'float',
        'total_eur' => 'float',
        'created_at' => 'datetime',
        'purge_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            $interdits = array_diff(array_keys($version->getDirty()), self::MUTABLES);
            if ($interdits !== []) {
                throw new LogicException('Une version de timesheet est immuable (champs : '.implode(', ', $interdits).').');
            }
        });

        static::deleting(function () {
            throw new LogicException('Une version de timesheet ne se supprime pas.');
        });
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** Lignes figées, décodées. @return list<array<string, mixed>> */
    public function lignesDecodees(): array
    {
        return $this->lignes === null ? [] : json_decode($this->lignes, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function cleActive(int $professeurId, int $annee, int $mois, string $type): string
    {
        return "{$professeurId}:{$annee}-{$mois}:{$type}";
    }
}
