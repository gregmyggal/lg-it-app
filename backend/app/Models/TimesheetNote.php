<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TS-06 : note interne (direction et admin). Ne doit JAMAIS apparaître dans une réponse destinée au professeur, ni dans
 * un e-mail, un PDF, une signature ou un snapshot de version : n'exposer que via `TimesheetNoteService`, côté staff.
 */
class TimesheetNote extends Model
{
    public const TAILLE_MAX = 1000;

    protected $fillable = ['professeur_id', 'annee', 'mois', 'timesheet_id', 'cle', 'auteur_id', 'texte'];

    protected $casts = ['annee' => 'integer', 'mois' => 'integer'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function historique(): HasMany
    {
        return $this->hasMany(TimesheetNoteHistorique::class, 'note_id');
    }

    public static function cle(int $professeurId, int $annee, int $mois, ?int $timesheetId): string
    {
        return $timesheetId === null ? "m:{$professeurId}:{$annee}-{$mois}" : "l:{$timesheetId}";
    }
}
