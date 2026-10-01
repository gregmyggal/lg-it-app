<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Professeur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prenom',
        'nom',
        'email',
        'telephone',
        'statut',
        'date_entree',
        'date_sortie',
        'type_contrat',
        'photo_path',
    ];

    protected $casts = [
        'date_entree' => 'date',
        'date_sortie' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Assignations aux classes (actives et terminées). */
    public function assignations(): HasMany
    {
        return $this->hasMany(ProfesseurClasse::class);
    }

    /** Classes assignées (actives et terminées), avec rôle et dates en pivot. */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'professeur_classe')
            ->withPivot('role', 'date_debut', 'date_fin');
    }

    /**
     * Cours des classes sur lesquelles le professeur a une assignation ACTIVE (date_fin null ou ≥ aujourd'hui).
     * Retourne un Builder (et non une relation) : le lien cours ⇄ professeur passe par la classe.
     *
     * @return Builder<Cours>
     */
    public function cours(): Builder
    {
        return Cours::query()->whereIn('cours.id', Classe::query()
            ->select('classes.cours_id')
            ->whereIn('classes.id', ProfesseurClasse::query()
                ->select('classe_id')
                ->where('professeur_id', $this->id)
                ->actif()));
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(ProfesseurTarif::class);
    }

    // Récupère le tarif horaire actuel (le plus récent)
    public function tarifCourant(): ?ProfesseurTarif
    {
        return $this->tarifs()
            ->where('date_debut', '<=', now())
            ->where(function ($query) {
                $query->whereNull('date_fin')
                    ->orWhere('date_fin', '>', now());
            })
            ->latest('date_debut')
            ->first();
    }

    /** Accès accordé si le professeur a une assignation active sur au moins une classe de ce cours (RG-6). */
    /** A (ou a eu) une assignation sur une classe de ce cours : consultation de l'historique des liens (T4). */
    public function aEuAssignationSurCours(Cours $cours): bool
    {
        return $this->assignations()
            ->whereIn('classe_id', Classe::query()->select('id')->where('cours_id', $cours->id))
            ->exists();
    }

    public function canAccessCours(Cours $cours): bool
    {
        return $this->cours()->where('cours.id', $cours->id)->exists();
    }
}
