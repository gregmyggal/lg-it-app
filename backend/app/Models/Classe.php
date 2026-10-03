<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Classe extends Model
{
    use HasFactory;

    public const STATUT_ACTIVE = 'active';

    public const STATUT_TERMINEE = 'terminee';

    public const STATUT_ARCHIVEE = 'archivee';

    public const STATUTS = [self::STATUT_ACTIVE, self::STATUT_TERMINEE, self::STATUT_ARCHIVEE];

    protected $table = 'classes';

    protected $fillable = [
        'annee_scolaire_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'lieu',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'jour_semaine' => 'integer',
        ];
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    /** Périodes de la classe (1 ou 2), triées par numéro de période. */
    public function periodes(): HasMany
    {
        return $this->hasMany(ClassePeriode::class)
            ->orderBy(Periode::select('numero')->whereColumn('periodes.id', 'classe_periodes.periode_id'))
            ->orderBy('id');
    }

    /** Périodes non annulées. */
    public function periodesActives(): HasMany
    {
        return $this->periodes()->where('statut', ClassePeriode::STATUT_ACTIVE);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class)->orderBy('seance_numero')->orderBy('bis_rang');
    }

    /** Sessions non annulées (utilisé pour nb_sessions et la règle de dépassement). */
    public function sessionsActives(): HasMany
    {
        return $this->hasMany(CourseSession::class)->where('statut', '!=', CourseSession::STATUT_ANNULEE);
    }

    /** Prochaine session non annulée à partir d'aujourd'hui (eager-loadable). */
    public function prochaineSession(): HasOne
    {
        return $this->hasOne(CourseSession::class)->ofMany(
            ['date' => 'min', 'id' => 'min'],
            fn ($query) => $query
                ->where('date', '>=', now('Europe/Brussels')->toDateString())
                ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
        );
    }

    /**
     * Relations (et agrégats par période) nécessaires à ClasseResource : à passer à with() / load().
     * Le compteur global `sessions_actives_count` s'ajoute avec withCount()/loadCount('sessionsActives').
     *
     * @return array<int|string, mixed>
     */
    public static function relationsResource(): array
    {
        return [
            'anneeScolaire.periodes',
            'periodes' => self::chargerPeriodes(),
            'prochaineSession.classePeriode.periode',
            'assignationsActives.professeur',
        ];
    }

    /** Eager load des périodes de classe avec leur période, leur cours et leurs agrégats de sessions. */
    public static function chargerPeriodes(): \Closure
    {
        return fn ($q) => $q->with(['periode', 'cours'])
            ->withCount(['sessionsActives', 'historiqueCours'])
            ->withMax('sessionsActives as derniere_session_date', 'date')
            ->withCount(['sessionsActives as nb_hors_periode' => fn ($s) => $s->whereRaw(\App\Services\PeriodeRegles::sqlHorsPeriode())]);
    }

    public function assignations(): HasMany
    {
        return $this->hasMany(ProfesseurClasse::class);
    }

    /** Assignations actives (date_fin null ou ≥ aujourd'hui), principal en premier. */
    public function assignationsActives(): HasMany
    {
        return $this->hasMany(ProfesseurClasse::class)->actif()->orderBy('id');
    }

    /**
     * Isolation (RG-5) : le staff voit tout ; un professeur ne voit que les classes où il a (ou a eu)
     * une assignation ; tout autre rôle ne voit rien.
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

        // Assignation (actuelle ou passée) OU remplacement ponctuel sur l'une des sessions de la classe.
        return $query->where(fn (Builder $q) => $q
            ->whereHas('assignations', fn (Builder $a) => $a->where('professeur_id', $professeurId))
            ->orWhereHas('sessions.sessionProfesseurs', fn (Builder $l) => $l
                ->where('professeur_id', $professeurId)->where('remplace', false)));
    }
}
