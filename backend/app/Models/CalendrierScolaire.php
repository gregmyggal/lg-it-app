<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendrierScolaire extends Model
{
    use HasFactory;

    public const TYPE_VACANCES = 'vacances';

    public const TYPE_FERIE = 'ferie';

    public const TYPE_FERMETURE = 'fermeture';

    public const TYPES = [self::TYPE_VACANCES, self::TYPE_FERIE, self::TYPE_FERMETURE];

    public const SOURCE_FWB = 'fwb';

    public const SOURCE_ECOLE = 'ecole';

    public const SOURCES = [self::SOURCE_FWB, self::SOURCE_ECOLE];

    protected $table = 'calendrier_scolaire';

    protected $fillable = [
        'annee_scolaire_id',
        'date_debut',
        'date_fin',
        'type',
        'libelle',
        'source',
        'masque',
        'cle_fwb',
        'modifie_manuellement',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
            'masque' => 'boolean',
            'modifie_manuellement' => 'boolean',
        ];
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /** Entrées visibles (non masquées) — seules celles-ci sont prises en compte par la génération. */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('masque', false);
    }

    public function isFwb(): bool
    {
        return $this->source === self::SOURCE_FWB;
    }
}
