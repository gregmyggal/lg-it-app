<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Anniversaire extends Model
{
    protected $fillable = [
        'nom_theme',
        'slug',
        'description',
        'tranche_age',
        'tarif',
        'image_path',
        'url_logiscool',
        'inclus',
        'options',
        'menu_order',
        'statut',
        'section_apropos',
        'section_deroulement',
        'section_pourquoi',
        'sidebar_tarification',
        'sidebar_inclus',
        'sidebar_options',
    ];

    protected $casts = [
        'tarif' => 'decimal:2',
        'section_deroulement' => 'array',
        'section_pourquoi' => 'array',
        'sidebar_tarification' => 'array',
        'sidebar_inclus' => 'array',
    ];

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
