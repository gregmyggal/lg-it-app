<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Professeur d'une session : propagé depuis la classe (origine classe) ajouté par un remplacement ou ajouté ponctuellement. */
class SessionProfesseur extends Model
{
    use HasFactory;

    public const ORIGINE_CLASSE = 'classe';

    public const ORIGINE_REMPLACEMENT = 'remplacement';

    /** Professeur ajouté ponctuellement à UNE session (sans remplacer personne, sans être assigné à la classe). */
    public const ORIGINE_AJOUT = 'ajout';

    protected $table = 'session_professors';

    protected $fillable = [
        'course_session_id',
        'professeur_id',
        'role',
        'origine',
        'remplace',
        'remplace_par_professeur_id',
    ];

    protected function casts(): array
    {
        return ['remplace' => 'boolean'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function remplacePar(): BelongsTo
    {
        return $this->belongsTo(Professeur::class, 'remplace_par_professeur_id');
    }
}
