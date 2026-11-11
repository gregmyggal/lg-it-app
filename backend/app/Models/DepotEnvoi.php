<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DEP-01 : trace d'un envoi du lien (adresse masquée, jamais en clair). */
class DepotEnvoi extends Model
{
    public $timestamps = false;

    protected $table = 'depot_envois';

    protected $fillable = ['partage_id', 'destinataire_masque', 'envoye_par', 'envoye_le'];

    protected function casts(): array
    {
        return ['envoye_le' => 'datetime'];
    }

    public function partage(): BelongsTo
    {
        return $this->belongsTo(DepotPartage::class, 'partage_id');
    }

    public function expediteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoye_par');
    }
}
