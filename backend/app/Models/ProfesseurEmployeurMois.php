<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** EMP-01 : employeur explicite d'un animateur pour un mois civil (sans ligne : résolu par héritage, voir EmployeurMoisService). */
class ProfesseurEmployeurMois extends Model
{
    use HasFactory;

    protected $table = 'professeur_employeurs_mois';

    public const SOURCE_EXPLICITE = 'explicite';

    public const SOURCE_MIGRATION = 'migration';

    public const SOURCE_FIGE = 'fige';

    protected $fillable = ['professeur_id', 'annee', 'mois', 'employeur_id', 'source', 'version', 'updated_by'];

    protected $casts = ['annee' => 'integer', 'mois' => 'integer', 'version' => 'integer'];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function employeur(): BelongsTo
    {
        return $this->belongsTo(Employeur::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
