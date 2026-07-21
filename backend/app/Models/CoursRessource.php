<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursRessource extends Model
{
    protected $fillable = [
        'cours_id',
        'titre_ressource',
        'url_ressource',
        'type_ressource',
        'ordre',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }
}
