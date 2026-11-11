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

    /** Le thème d'un stage est toujours affiché avec la période. */
    protected $with = ['stage'];

    protected $fillable = [
        'classe_id',
        'periode_id',
        'cours_id',
        'stage_id',
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

    /** STG-01 : thème d'un stage planifié (fiche `stages`) ; null pour une classe, qui a un cours. */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** Titre du cours, ou du thème pour un stage. */
    public function titre(): ?string
    {
        return $this->cours?->titre ?? $this->stage?->titre;
    }

    /**
     * Forme « cours » exposée par l'API : le cours, ou le thème d'un stage (sans identifiant de cours : `id` null,
     * `stage_id` renseigné). Les écrans lisent `cours.titre` sans distinguer les deux.
     *
     * @return array{id: ?int, titre: string, slug: ?string, stage_id: ?int}|null
     */
    public function coursAffiche(): ?array
    {
        if ($this->cours) {
            return ['id' => $this->cours->id, 'titre' => $this->cours->titre, 'slug' => $this->cours->slug, 'stage_id' => null];
        }

        return $this->stage ? ['id' => null, 'titre' => $this->stage->titre, 'slug' => $this->stage->slug, 'stage_id' => $this->stage->id] : null;
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
