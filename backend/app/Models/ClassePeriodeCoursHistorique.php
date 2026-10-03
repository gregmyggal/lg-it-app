<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne de traçabilité d'un changement de cours (RG-5b). */
class ClassePeriodeCoursHistorique extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'classe_periode_cours_historique';

    protected $fillable = ['classe_periode_id', 'ancien_cours_id', 'nouveau_cours_id', 'user_id'];

    public function classePeriode(): BelongsTo
    {
        return $this->belongsTo(ClassePeriode::class);
    }

    public function ancienCours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'ancien_cours_id');
    }

    public function nouveauCours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'nouveau_cours_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
