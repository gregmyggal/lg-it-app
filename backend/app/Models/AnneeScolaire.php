<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeScolaire extends Model
{
    use HasFactory;

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ACTIVE = 'active';

    public const STATUT_ARCHIVEE = 'archivee';

    public const STATUTS = [self::STATUT_BROUILLON, self::STATUT_ACTIVE, self::STATUT_ARCHIVEE];

    protected $table = 'annees_scolaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'statut'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
        ];
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('numero');
    }

    public function calendrier(): HasMany
    {
        return $this->hasMany(CalendrierScolaire::class)->orderBy('date_debut');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
