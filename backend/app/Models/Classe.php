<?php

namespace App\Models;

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
        'cours_id',
        'annee_scolaire_id',
        'periode_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'lieu',
        'date_premiere_session',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'jour_semaine' => 'integer',
            'date_premiere_session' => 'date:Y-m-d',
        ];
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
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
}
