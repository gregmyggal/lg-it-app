<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une période (1 ou 2) d'une classe : un cours, une date de démarrage, 14 séances (CLS-02). */
class ClassePeriode extends Model
{
    use HasFactory;

    public const STATUT_ACTIVE = 'active';

    public const STATUT_ANNULEE = 'annulee';

    protected $table = 'classe_periodes';

    protected $fillable = [
        'classe_id',
        'periode_id',
        'cours_id',
        'date_premiere_session',
        'statut',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return ['date_premiere_session' => 'date:Y-m-d'];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class)->orderBy('seance_numero')->orderBy('bis_rang');
    }

    public function historiqueCours(): HasMany
    {
        return $this->hasMany(ClassePeriodeCoursHistorique::class)->orderByDesc('id');
    }

    public function sessionsActives(): HasMany
    {
        return $this->hasMany(CourseSession::class)->where('statut', '!=', CourseSession::STATUT_ANNULEE);
    }

    public function isAnnulee(): bool
    {
        return $this->statut === self::STATUT_ANNULEE;
    }
}
