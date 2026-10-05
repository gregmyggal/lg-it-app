<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SIG-01 : signature d'un mois par le professeur. Immuable : le contenu signé (payload canonique), son empreinte,
 * le sceau Ed25519 du serveur et une copie figée de l'image. Une nouvelle signature du même mois la « remplace ».
 */
class TimesheetSignature extends Model
{
    public const STATUT_VALIDE = 'valide';

    public const STATUT_REMPLACEE = 'remplacee';

    protected $fillable = [
        'public_id', 'professeur_id', 'user_id', 'annee', 'mois', 'statut', 'signed_at', 'ip', 'user_agent', 'payload',
        'content_hash', 'seal', 'key_id', 'specimen_type', 'specimen_chemin', 'verification_publique', 'anonymisee_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'anonymisee_at' => 'datetime',
        'verification_publique' => 'boolean',
        'annee' => 'integer',
        'mois' => 'integer',
    ];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
