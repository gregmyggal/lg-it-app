<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cours extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'slug',
        'contenu',
        'extrait',
        'image_path',
        'url_logiscool',
        'menu_order',
        'statut',
        'section_apropos',
        'section_apprendras',
        'section_format',
        'section_pourqui',
        'sidebar_pratiques',
        'sidebar_benefits',
    ];

    protected $casts = [
        'section_apprendras' => 'array',
        'section_format' => 'array',
        'section_pourqui' => 'array',
        'sidebar_pratiques' => 'array',
        'sidebar_benefits' => 'array',
    ];

    public function typesCours(): BelongsToMany
    {
        return $this->belongsToMany(TypeCours::class, 'cours_type_cours');
    }

    /**
     * Professeurs actuellement assignés à ce cours.
     *
     * Inclut principal + co-enseignants + remplaçants tant que leur assignation est active.
     */
    public function professeurs(): BelongsToMany
    {
        return $this->belongsToMany(Professeur::class, 'professeur_cours')
            ->withPivot('role', 'date_debut', 'date_fin')
            ->where(function ($query) {
                $query->whereNull('professeur_cours.date_fin')
                    ->orWhere('professeur_cours.date_fin', '>', now());
            })
            ->orderByPivot('role'); // principal en premier
    }

    /**
     * Historique complet de tous les professeurs assignés à ce cours.
     */
    public function professeursHistorique(): BelongsToMany
    {
        return $this->belongsToMany(Professeur::class, 'professeur_cours')
            ->withPivot('role', 'date_debut', 'date_fin')
            ->orderByPivot('date_debut', 'desc');
    }

    /**
     * Le professeur principal actuellement assigné à ce cours.
     */
    public function professeurPrincipal(): ?Professeur
    {
        return $this->professeurs()
            ->wherePivot('role', 'principal')
            ->first();
    }

    /**
     * Tous les co-professeurs (co-enseignants + remplaçants) assignés à ce cours.
     */
    public function coProfesseurs(): Collection
    {
        return $this->professeurs()
            ->whereIn('professeur_cours.role', ['co-enseignant', 'remplaçant'])
            ->get();
    }

    public function ressources(): HasMany
    {
        return $this->hasMany(CoursRessource::class)->orderBy('ordre');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    /**
     * Classes (organisations de ce cours pour une année scolaire).
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
