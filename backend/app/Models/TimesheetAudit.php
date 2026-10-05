<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetAudit extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_ADAPTATION = 'adaptation';

    public const ACTION_LISSAGE = 'lissage';

    public const ACTION_CONTESTATION = 'contestation';

    public const ACTION_REPONSE = 'reponse_contestation';

    public const ACTION_DEVERROUILLAGE = 'deverrouillage';

    /** CLS-08 : heure détachée de sa séance lors de la suppression forcée de la classe. */
    public const ACTION_CLASSE_SUPPRIMEE = 'classe_supprimee';

    protected $fillable = ['timesheet_id', 'professeur_id', 'user_id', 'action', 'avant', 'apres', 'motif'];

    protected $casts = ['avant' => 'array', 'apres' => 'array'];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
