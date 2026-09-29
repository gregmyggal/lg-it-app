<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionCalendarView extends Model
{
    protected $fillable = [
        'user_id',
        'nom',
        'vue_defaut',
        'filtres',
    ];

    protected $casts = [
        'filtres' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFilters(): array
    {
        return $this->filtres ?? [];
    }

    public function setFilters(array $filters): void
    {
        $this->filtres = $filters;
        $this->save();
    }
}
