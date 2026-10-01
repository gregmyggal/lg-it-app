<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClasseLien extends Model
{
    use SoftDeletes;

    /** Alias de morphologie du parent « cours » (voir AppServiceProvider::morphMap). */
    public const PARENT_COURS = 'cours';

    public const TYPES = ['document', 'video', 'outil', 'jeu'];

    public const NB_SEANCES = 14;

    protected $fillable = [
        'parent_type',
        'parent_id',
        'titre',
        'url',
        'description',
        'theme',
        'seance',
        'seance_numero',
        'version',
        'created_by',
        'updated_by',
        'ordre',
        'actif',
        'pinned',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'pinned' => 'boolean',
    ];

    public function auteurModification(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Lien rattaché à une séance qui n'existe pas (au-delà de la 14ᵉ) : affiché dans « Hors programme ». */
    public function estHorsProgramme(): bool
    {
        return $this->seance_numero !== null && $this->seance_numero > self::NB_SEANCES;
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
