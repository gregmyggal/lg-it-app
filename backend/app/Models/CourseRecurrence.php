<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseRecurrence extends Model
{
    protected $fillable = [
        'cours_id',
        'type',
        'jours_semaine',
        'date_debut',
        'date_fin',
        'heure_debut',
        'heure_fin',
        'lieu_defaut',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class, 'recurrence_id');
    }

    public function isActive(): bool
    {
        return $this->statut === 'active';
    }

    public function getDaysOfWeek(): array
    {
        if (!$this->jours_semaine) {
            return [];
        }
        return array_map('intval', explode(',', $this->jours_semaine));
    }
}
