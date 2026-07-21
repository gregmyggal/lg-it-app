<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClasseSetting extends Model
{
    protected $fillable = [
        'parent_type',
        'parent_id',
        'seance_active',
        'access_code',
    ];

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
