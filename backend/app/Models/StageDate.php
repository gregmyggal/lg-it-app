<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageDate extends Model
{
    protected $fillable = ['stage_id', 'date_session'];

    protected $casts = [
        'date_session' => 'date',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
