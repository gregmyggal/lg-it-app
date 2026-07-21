<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Professeur extends Model
{
    protected $fillable = [
        'user_id',
        'prenom',
        'nom',
        'email',
        'telephone',
        'statut',
        'date_entree',
        'date_sortie',
        'type_contrat',
        'photo_path',
    ];

    protected $casts = [
        'date_entree' => 'date',
        'date_sortie' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Détermine les cours que le professeur est habilité à voir/modifier (isolation).
    public function typesCours(): BelongsToMany
    {
        return $this->belongsToMany(TypeCours::class, 'professeur_type_cours');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    // Accès accordé si au moins un type_cours du cours correspond à un type assigné au professeur
    // (portage de LGIT_Professor_Isolation::user_can_access_cours).
    public function canAccessCours(Cours $cours): bool
    {
        $allowedIds = $this->typesCours()->pluck('types_cours.id');

        if ($allowedIds->isEmpty()) {
            return false;
        }

        $coursTypeIds = $cours->typesCours()->pluck('types_cours.id');

        return $allowedIds->intersect($coursTypeIds)->isNotEmpty();
    }
}
