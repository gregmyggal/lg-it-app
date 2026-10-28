<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** TS-06 : version remplacée d'une note interne (lecture seule). */
class TimesheetNoteHistorique extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'timesheet_note_historique';

    protected $fillable = ['note_id', 'texte', 'auteur_id'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
