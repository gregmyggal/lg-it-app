<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Stage extends Model
{
    protected $fillable = [
        'titre',
        'slug',
        'theme_stage',
        'tranche_age',
        'lieu',
        'prix',
        'description',
        'image_path',
        'url_logiscool',
        'sessions_vacances',
        'menu_order',
        'statut',
        'section_apropos',
        'section_programme',
        'section_strengths',
        'sidebar_infos',
        'sidebar_inclus',
    ];

    protected $casts = [
        'sessions_vacances' => 'array',
        'prix' => 'decimal:2',
        'section_programme' => 'array',
        'section_strengths' => 'array',
        'sidebar_infos' => 'array',
        'sidebar_inclus' => 'array',
    ];

    public function dates(): HasMany
    {
        return $this->hasMany(StageDate::class)->orderBy('date_session');
    }

    public function liensClasse(): MorphMany
    {
        return $this->morphMany(ClasseLien::class, 'parent')->orderBy('ordre');
    }
}
