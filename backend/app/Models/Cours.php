<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Cours extends Model
{
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

    public function ressources(): HasMany
    {
        return $this->hasMany(CoursRessource::class)->orderBy('ordre');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
