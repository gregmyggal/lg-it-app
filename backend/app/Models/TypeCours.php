<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TypeCours extends Model
{
    protected $table = 'types_cours';

    protected $fillable = ['nom', 'slug'];

    public function cours(): BelongsToMany
    {
        return $this->belongsToMany(Cours::class, 'cours_type_cours');
    }

    public function professeurs(): BelongsToMany
    {
        return $this->belongsToMany(Professeur::class, 'professeur_type_cours');
    }
}
