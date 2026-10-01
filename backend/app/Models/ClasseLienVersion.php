<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Version d'un lien de cours (historique append-only, conservée 6 mois). */
class ClasseLienVersion extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATION = 'creation';

    public const ACTION_MODIFICATION = 'modification';

    public const ACTION_PORTEE = 'portee';

    public const ACTION_ARCHIVAGE = 'archivage';

    public const ACTION_ORDRE = 'ordre';

    public const ACTION_RESTAURATION = 'restauration';

    public const ACTIONS = [
        self::ACTION_CREATION, self::ACTION_MODIFICATION, self::ACTION_PORTEE,
        self::ACTION_ARCHIVAGE, self::ACTION_ORDRE, self::ACTION_RESTAURATION,
    ];

    protected $table = 'classe_liens_historique';

    protected $fillable = ['classe_lien_id', 'parent_type', 'parent_id', 'action', 'avant', 'apres', 'user_id', 'restaure_depuis_id'];

    protected $casts = ['avant' => 'array', 'apres' => 'array', 'created_at' => 'datetime'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Une version de plus de 6 mois n'est plus restaurable (purgée la nuit suivante). */
    public function estExpiree(): bool
    {
        return $this->created_at->lt(now()->subMonths(6));
    }
}
