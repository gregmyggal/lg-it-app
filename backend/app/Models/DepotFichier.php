<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DEP-01 : fichier déposé (réalisation d'un enfant). Le contenu vit sur le disque privé, jamais servi tel quel. */
class DepotFichier extends Model
{
    protected $table = 'depot_fichiers';

    protected $fillable = ['depot_espace_id', 'nom_original', 'chemin', 'mime', 'taille', 'libelle', 'depose_par'];

    protected function casts(): array
    {
        return ['taille' => 'integer'];
    }

    public function espace(): BelongsTo
    {
        return $this->belongsTo(DepotEspace::class, 'depot_espace_id');
    }

    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }
}
