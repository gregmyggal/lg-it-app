<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClasseLien extends Model
{
    protected $fillable = [
        'parent_type',
        'parent_id',
        'titre',
        'url',
        'description',
        'theme',
        'seance',
        'ordre',
        'actif',
        'pinned',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'pinned' => 'boolean',
    ];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
