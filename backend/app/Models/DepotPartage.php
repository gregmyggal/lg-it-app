<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** DEP-01 : lien parent. Le token en clair n'est jamais stocké (hash + copie chiffrée). */
class DepotPartage extends Model
{
    public const STATUT_ACTIF = 'actif';

    public const STATUT_EXPIRE = 'expire';

    public const STATUT_REVOQUE = 'revoque';

    protected $table = 'depot_partages';

    protected $fillable = [
        'classe_id', 'libelle', 'token_hash', 'token_chiffre', 'expire_le', 'revoque_le', 'cree_par', 'nb_ouvertures', 'derniere_ouverture_le',
    ];

    protected $hidden = ['token_hash', 'token_chiffre'];

    protected function casts(): array
    {
        return ['expire_le' => 'datetime', 'revoque_le' => 'datetime', 'derniere_ouverture_le' => 'datetime', 'nb_ouvertures' => 'integer'];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function fichiers(): BelongsToMany
    {
        return $this->belongsToMany(DepotFichier::class, 'depot_partage_fichiers', 'partage_id', 'fichier_id');
    }

    /** Calculé (jamais stocké) : la révocation prime sur l'expiration. */
    public function statut(): string
    {
        if ($this->revoque_le !== null) {
            return self::STATUT_REVOQUE;
        }

        return $this->expire_le->isPast() ? self::STATUT_EXPIRE : self::STATUT_ACTIF;
    }
}
