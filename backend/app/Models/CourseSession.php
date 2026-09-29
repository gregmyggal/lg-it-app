<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseSession extends Model
{
    protected $fillable = [
        'cours_id',
        'recurrence_id',
        'date_debut',
        'heure_debut',
        'heure_fin',
        'titre',
        'lieu',
        'description',
        'statut',
        'motif_annulation',
        'professor_principal_id',
        'nb_eleves_attendus',
        'nb_eleves_presentes',
        'cancelled_at',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
        'cancelled_at' => 'datetime',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(CourseRecurrence::class);
    }

    public function professorPrincipal(): BelongsTo
    {
        return $this->belongsTo(Professeur::class, 'professor_principal_id');
    }

    public function sessionProfessors(): HasMany
    {
        return $this->hasMany(SessionProfessor::class, 'course_session_id');
    }

    public function professeurs(): BelongsToMany
    {
        return $this->belongsToMany(
            Professeur::class,
            'session_professors',
            'course_session_id',
            'professeur_id'
        )->withPivot('role', 'present', 'motif_absence');
    }

    public function isScheduled(): bool
    {
        return $this->statut === 'scheduled';
    }

    public function isInProgress(): bool
    {
        return $this->statut === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->statut === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->statut === 'cancelled';
    }

    public function cancel(string $reason = null): void
    {
        $this->update([
            'statut' => 'cancelled',
            'motif_annulation' => $reason,
            'cancelled_at' => now(),
        ]);
    }

    public function getDisplayTitle(): string
    {
        return $this->titre ?? $this->cours->titre;
    }
}
