<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** EMP-01 (RG-9) : journal append-only des changements d'employeur d'un mois. */
class EmployeurMoisAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['professeur_id', 'annee', 'mois', 'employeur_avant_id', 'employeur_apres_id', 'motif', 'user_id'];

    protected $casts = ['annee' => 'integer', 'mois' => 'integer'];

    public function avant(): BelongsTo
    {
        return $this->belongsTo(Employeur::class, 'employeur_avant_id');
    }

    public function apres(): BelongsTo
    {
        return $this->belongsTo(Employeur::class, 'employeur_apres_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
