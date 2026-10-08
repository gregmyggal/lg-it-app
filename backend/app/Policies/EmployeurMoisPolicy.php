<?php

namespace App\Policies;

use App\Models\Professeur;
use App\Models\ProfesseurEmployeurMois;
use App\Models\User;

/**
 * EMP-01 : employeur d'un mois. Le staff lit et modifie (les verrous sont une règle métier du service) ;
 * le professeur lit uniquement ses propres mois (nom de l'entité), jamais ceux d'un autre, ni l'historique.
 *
 * Usage : Gate::authorize('view', [ProfesseurEmployeurMois::class, $professeur]).
 */
class EmployeurMoisPolicy
{
    public function view(User $user, Professeur $professeur): bool
    {
        return $user->isStaff() || ($user->isProfesseur() && $professeur->user_id === $user->id);
    }

    public function update(User $user, ?Professeur $professeur = null): bool
    {
        return $user->isStaff();
    }

    public function viewHistorique(User $user, Professeur $professeur): bool
    {
        return $user->isStaff();
    }
}
