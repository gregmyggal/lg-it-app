<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'classe_periode_id',
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

    public function classePeriode(): BelongsTo
    {
        return $this->belongsTo(ClassePeriode::class);
    }

    public function remplace(): BelongsTo
    {
        return $this->belongsTo(self::class, 'remplace_session_id');
    }

    public function sessionProfesseurs(): HasMany
    {
        return $this->hasMany(SessionProfesseur::class, 'course_session_id')->orderBy('id');
    }

    /**
     * Isolation (RG-5) : le staff voit tout ; un professeur voit les sessions où il a une ligne non
     * « remplacée », ou les sessions de sa classe pendant la durée de son assignation ; tout autre rôle rien.
     */
    public function scopeVisiblePour(Builder $query, ?User $user): Builder
    {
        if ($user?->isStaff()) {
            return $query;
        }

        if (! $user?->isProfesseur() || ! $user->professeur) {
            return $query->whereRaw('1 = 0');
        }

        $professeurId = $user->professeur->id;

        return $query->where(function (Builder $q) use ($professeurId) {
            $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('session_professors')
                ->whereColumn('session_professors.course_session_id', 'course_sessions.id')
                ->where('session_professors.professeur_id', $professeurId)
                ->where('session_professors.remplace', false))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('professeur_classe')
                    ->whereColumn('professeur_classe.classe_id', 'course_sessions.classe_id')
                    ->where('professeur_classe.professeur_id', $professeurId)
                    ->whereColumn('professeur_classe.date_debut', '<=', 'course_sessions.date')
                    ->where(fn ($d) => $d->whereNull('professeur_classe.date_fin')
                        ->orWhereColumn('professeur_classe.date_fin', '>=', 'course_sessions.date')));
        });
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

    /** « P1 · Séance 3 » / « P1 · Séance 3 bis » (période requise : classePeriode.periode chargé). */
    public function libelleComplet(): string
    {
        $numero = $this->classePeriode?->periode?->numero;

        return $numero ? "P{$numero} · ".$this->libelle() : $this->libelle();
    }

    /** Séance datée après la fin de la période de classe (rattrapage, retard) ; calculé, jamais persisté. */
    public function isHorsPeriode(): bool
    {
        $fin = $this->classePeriode?->periode?->date_fin;

        return $fin !== null && \App\Services\PeriodeRegles::horsPeriode($this->date->toDateString(), $fin->toDateString());
    }

    /** @return list<string> avertissements non bloquants (jamais d'erreur après la fin de période). */
    public function avertissements(): array
    {
        return $this->isHorsPeriode()
            ? ["Cette date est après la fin de la période {$this->classePeriode->periode->numero}"]
            : [];
    }
}
