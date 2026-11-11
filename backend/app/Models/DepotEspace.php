<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DEP-01 : dossier privé d'un stage (course_session_id NULL) ou d'une de ses séances. */
class DepotEspace extends Model
{
    protected $table = 'depot_espaces';

    protected $fillable = ['classe_id', 'course_session_id'];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(DepotFichier::class, 'depot_espace_id');
    }
}
