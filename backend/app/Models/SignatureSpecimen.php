<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SIG-01 : signature réutilisable d'un professeur (PNG transparent : nom manuscrit, dessin ou initiales). */
class SignatureSpecimen extends Model
{
    public const TYPES = ['nom', 'dessin', 'initiales'];

    public const POLICES = ['Dancing Script', 'Caveat', 'Great Vibes'];

    public const COULEURS = ['#1a3d8f', '#111111'];

    protected $fillable = ['user_id', 'type', 'texte', 'police', 'couleur', 'chemin', 'sha256', 'consenti_at'];

    protected $casts = ['consenti_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
