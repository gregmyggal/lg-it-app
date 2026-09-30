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

    /**
     * Cours actuellement assignés à ce professeur (assignments actives).
     *
     * Un cours est "actif" si:
     * - date_fin est NULL (pas de fin), OU
     * - date_fin est dans le futur (pas encore révolu)
     */
    public function cours(): BelongsToMany
    {
        return $this->belongsToMany(Cours::class, 'professeur_cours')
            ->withPivot('role', 'date_debut', 'date_fin')
            ->where(function ($query) {
                $query->whereNull('professeur_cours.date_fin')
                    ->orWhere('professeur_cours.date_fin', '>', now());
            })
            ->orderByPivot('role'); // principal en premier
    }

    /**
     * Historique complet de tous les cours assignés à ce professeur (y compris archivés).
     */
    public function coursHistorique(): BelongsToMany
    {
        return $this->belongsToMany(Cours::class, 'professeur_cours')
            ->withPivot('role', 'date_debut', 'date_fin')
            ->orderByPivot('date_debut', 'desc');
    }

    // Détermine les cours que le professeur est habilité à voir/modifier (isolation).
    // LEGACY: Peut être remplacé par cours() pour isolation fine, mais garder pour sécurité en double
    public function typesCours(): BelongsToMany
    {
        return $this->belongsToMany(TypeCours::class, 'professeur_type_cours');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(ProfesseurTarif::class);
    }

    // Récupère le tarif horaire actuel (le plus récent)
    public function tarifCourant(): ?ProfesseurTarif
    {
        return $this->tarifs()
            ->where('date_debut', '<=', now())
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>', now());
            })
            ->latest('date_debut')
            ->first();
    }

    /**
     * Accès accordé si le professeur est actuellement assigné à ce cours.
     *
     * NEW (v2): Vérification fine au niveau du cours spécifique (pas juste par type).
     * Remplace l'ancienne logique typesCours pour isolation plus granulaire.
     */
    public function canAccessCours(Cours $cours): bool
    {
        return $this->cours()
            ->where('cours.id', $cours->id)
            ->exists();
    }

    /**
     * LEGACY: Accès selon les types de cours (pour sécurité en double ou compatibilité).
     * À court terme, les deux logiques coexistent (cours + typesCours).
     */
    public function canAccessCoursByType(Cours $cours): bool
    {
        $allowedIds = $this->typesCours()->pluck('types_cours.id');

        if ($allowedIds->isEmpty()) {
            return false;
        }

        $coursTypeIds = $cours->typesCours()->pluck('types_cours.id');

        return $allowedIds->intersect($coursTypeIds)->isNotEmpty();
    }
}
