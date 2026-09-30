<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSession extends Model
{
    use HasFactory;

    public const STATUT_PLANIFIEE = 'planifiee';

    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TERMINEE = 'terminee';

    public const STATUT_ANNULEE = 'annulee';

    public const STATUTS = [
        self::STATUT_PLANIFIEE,
        self::STATUT_EN_COURS,
        self::STATUT_TERMINEE,
        self::STATUT_ANNULEE,
    ];

    protected $table = 'course_sessions';

    protected $fillable = [
        'classe_id',
        'seance_numero',
        'bis_rang',
        'remplace_session_id',
        'date',
        'heure_debut',
        'heure_fin',
        'lieu',
        'statut',
        'motif_annulation',
        'cancelled_at',
    ];

    /** Renseignée par CalendrierScolaireService::attachAlerts() (jamais persistée). */
    public ?array $alerte_calendrier = null;

    protected function casts(): array
    {
        return [
            'seance_numero' => 'integer',
            'bis_rang' => 'integer',
            'date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function remplace(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remplace_session_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'course_session_id');
    }

    public function isAnnulee(): bool
    {
        return $this->statut === self::STATUT_ANNULEE;
    }

    /** Une session active compte dans le nombre de sessions de la classe (annulée = non). */
    public function isActive(): bool
    {
        return ! $this->isAnnulee();
    }

    public function isPassee(): bool
    {
        return $this->statut === self::STATUT_TERMINEE
            || $this->date->toDateString() < now('Europe/Brussels')->toDateString();
    }

    /** Déplaçable : ni passée, ni annulée. */
    public function isMovable(): bool
    {
        return ! $this->isAnnulee() && ! $this->isPassee();
    }

    public function isCancellable(): bool
    {
        return in_array($this->statut, [self::STATUT_PLANIFIEE, self::STATUT_EN_COURS], true);
    }

    public function libelle(): string
    {
        return $this->bis_rang > 0
            ? "Séance {$this->seance_numero} bis".($this->bis_rang > 1 ? " {$this->bis_rang}" : '')
            : "Séance {$this->seance_numero}";
    }
}
