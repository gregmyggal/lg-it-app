<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Formation extends Model
{
    protected $fillable = [
        'titre',
        'slug',
        'programme',
        'extrait',
        'image_path',
        'format',
        'duree',
        'prix',
        'public_cible',
        'niveau',
        'prochaine_date',
        'objectifs',
        'url_inscription',
        'menu_order',
        'statut',
        'section_apropos',
        'section_competences',
        'section_approche',
        'section_parcours',
        'sidebar_infos',
        'sidebar_public',
        'sidebar_resultats',
    ];

    protected $casts = [
        'prochaine_date' => 'date',
        'prix' => 'decimal:2',
        'section_competences' => 'array',
        'section_approche' => 'array',
        'section_parcours' => 'array',
        'sidebar_infos' => 'array',
        'sidebar_public' => 'array',
        'sidebar_resultats' => 'array',
    ];

    public function typesFormation(): BelongsToMany
    {
        return $this->belongsToMany(TypeFormation::class, 'formation_type_formation');
    }

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
