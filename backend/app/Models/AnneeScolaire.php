<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AnneeScolaire extends Model
{
    use HasFactory;

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_ARCHIVEE = 'archivee';

    public const STATUTS = [self::STATUT_BROUILLON, self::STATUT_ACTIVE, self::STATUT_ARCHIVEE];

    protected $table = 'annees_scolaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'statut', 'updated_by'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
        ];
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('numero');
    }

    public function calendrier(): HasMany
    {
        return $this->hasMany(CalendrierScolaire::class)->orderBy('date_debut');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function sessions(): HasManyThrough
    {
        return $this->hasManyThrough(CourseSession::class, Classe::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isArchivee(): bool
    {
        return $this->statut === self::STATUT_ARCHIVEE;
    }

    /** Compteurs et relations lus par AnneeScolaireResource (évite le N+1 de la liste). */
    public function scopeAvecCompteurs(Builder $query): Builder
    {
        return $query->with(['periodes', 'updatedBy:id,name'])
            ->withCount(['classes', 'sessions', 'calendrier as calendrier_count' => fn ($q) => $q->actives()]);
    }
}
