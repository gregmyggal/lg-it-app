<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'heures_defrayables',
        'statut',
        'section_apropos',
        'section_apprendras',
        'section_format',
        'section_pourqui',
        'sidebar_pratiques',
        'sidebar_benefits',
    ];

    protected $casts = [
        'heures_defrayables' => 'float',
        'section_apprendras' => 'array',
        'section_format' => 'array',
        'section_pourqui' => 'array',
        'sidebar_pratiques' => 'array',
        'sidebar_benefits' => 'array',
    ];

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
    public function classePeriodes(): HasMany
    {
        return $this->hasMany(ClassePeriode::class);
    }

    /** Classes ayant ce cours sur au moins une de leurs périodes. */
    public function classes(): \Illuminate\Database\Eloquent\Builder
    {
        return Classe::query()->whereHas('periodes', fn ($q) => $q->where('cours_id', $this->id));
    }

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
