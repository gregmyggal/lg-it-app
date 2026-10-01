<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    protected $fillable = [
        'professeur_id',
        'date_prestation',
        'nombre_heures',
        'type_activite',
        'cours_id',
        'course_session_id',
        'commentaire',
        'statut_validation',
        'lissage_applique',
        'validated_at',
        'validated_by',
        'signature_professeur',
        'pdf_generated_at',
        'pdf_generated_by',
    ];

    protected $casts = [
        'date_prestation' => 'date',
        'nombre_heures' => 'decimal:2',
        'validated_at' => 'datetime',
    ];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_SOUMIS = 'soumis';

    public const STATUT_CONFIRME = 'confirme';

    public const STATUT_GENERE = 'genere';

    public const STATUTS = [self::STATUT_BROUILLON, self::STATUT_SOUMIS, self::STATUT_CONFIRME, self::STATUT_GENERE];

    // Verrouillées : toute saisie qui n'est plus un brouillon (soumise, confirmée, générée).
    public function isLocked(): bool
    {
        return in_array($this->statut_validation, [self::STATUT_SOUMIS, self::STATUT_CONFIRME, self::STATUT_GENERE], true);
    }
}
