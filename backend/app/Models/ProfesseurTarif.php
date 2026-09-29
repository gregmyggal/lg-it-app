<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfesseurTarif extends Model
{
    protected $table = 'professeur_tarifs';

    protected $fillable = [
        'professeur_id',
        'tarif_horaire_eur',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'tarif_horaire_eur' => 'decimal:2',
    ];

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    // Récupère le tarif valide pour une date donnée
    // Exemple: ProfesseurTarif::effectiveAt($prof_id, $date_prestation)
    public static function effectiveAt(int $professeurId, \DateTime $date): ?self
    {
        return self::where('professeur_id', $professeurId)
            ->where('date_debut', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>', $date);
            })
            ->latest('date_debut')
            ->first();
    }
}
