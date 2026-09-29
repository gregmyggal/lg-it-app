<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionProfessor extends Model
{
    protected $fillable = [
        'course_session_id',
        'professeur_id',
        'role',
        'present',
        'motif_absence',
    ];

    protected $casts = [
        'present' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function isPrincipal(): bool
    {
        return $this->role === 'principal';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isSubstitute(): bool
    {
        return $this->role === 'substitute';
    }

    public function isObserver(): bool
    {
        return $this->role === 'observer';
    }
}
