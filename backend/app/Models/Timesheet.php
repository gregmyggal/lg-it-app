<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    protected $fillable = [
        'professeur_id',
        'date_prestation',
        'nombre_heures',
        'cours_id',
        'commentaire',
        'statut_validation',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'date_prestation' => 'date',
        'nombre_heures' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // Statuts 'soumis'/'valide' verrouillés — cf. règles côté service/policy (Phase 1).
    public function isLocked(): bool
    {
        return in_array($this->statut_validation, ['soumis', 'valide'], true);
    }
}
