<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assignation d'un professeur à une classe (la ligne est conservée une fois terminée : historique). */
class ProfesseurClasse extends Model
{
    use HasFactory;

    public const ROLE_PRINCIPAL = 'principal';

    public const ROLE_CO_ENSEIGNANT = 'co_enseignant';

    public const ROLE_REMPLACANT = 'remplacant';

    public const ROLES = [self::ROLE_PRINCIPAL, self::ROLE_CO_ENSEIGNANT, self::ROLE_REMPLACANT];

    protected $table = 'professeur_classe';

    protected $fillable = ['professeur_id', 'classe_id', 'role', 'date_debut', 'date_fin'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
        ];
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /** Active : pas de fin, ou fin aujourd'hui / dans le futur (Europe/Brussels). */
    public function scopeActif(Builder $query): Builder
    {
        $aujourdhui = now('Europe/Brussels')->toDateString();

        return $query->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('date_fin'))
            ->orWhere($this->qualifyColumn('date_fin'), '>=', $aujourdhui));
    }

    /** Active à une date donnée (début ≤ date ≤ fin éventuelle). */
    public function scopeActifA(Builder $query, string $date): Builder
    {
        return $query->where($this->qualifyColumn('date_debut'), '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('date_fin'))
                ->orWhere($this->qualifyColumn('date_fin'), '>=', $date));
    }

    public function isActif(): bool
    {
        return $this->date_fin === null || $this->date_fin->toDateString() >= now('Europe/Brussels')->toDateString();
    }
}
