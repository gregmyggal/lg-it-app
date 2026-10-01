<?php

namespace App\Policies;

use App\Models\ClasseLien;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClasseLienPolicy
{
    // Lecture : staff, ou tout professeur pour les liens d'un COURS (lecture seule utile à un remplaçant, Q-T4-9).
    public function viewAny(User $user, Model $parent): bool
    {
        return $this->peutLire($user, $parent);
    }

    public function view(User $user, ClasseLien $classeLien): bool
    {
        return $this->peutLire($user, $classeLien->parent);
    }

    // Écriture : staff, ou professeur avec une assignation ACTIVE sur une classe du cours (RG-6).
    public function create(User $user, Model $parent): bool
    {
        return $this->peutEcrire($user, $parent);
    }

    public function update(User $user, ClasseLien $classeLien): bool
    {
        return $this->peutEcrire($user, $classeLien->parent);
    }

    public function delete(User $user, ClasseLien $classeLien): bool
    {
        return $this->peutEcrire($user, $classeLien->parent);
    }

    /** Restaurer une version : mêmes droits que l'écriture. */
    public function restore(User $user, ClasseLien $classeLien): bool
    {
        return $this->peutEcrire($user, $classeLien->parent);
    }

    /** Historique : staff, ou professeur qui a (ou a eu) une assignation sur une classe du cours. */
    public function viewHistory(User $user, Model $parent): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->isProfesseur() && $user->professeur && $parent instanceof Cours
            && $user->professeur->aEuAssignationSurCours($parent);
    }

    private function peutLire(User $user, ?Model $parent): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        // Stages, formations, anniversaires : toujours refusé au professeur (comportement historique, hors T4).
        return $user->isProfesseur() && $user->professeur !== null && $parent instanceof Cours;
    }

    private function peutEcrire(User $user, ?Model $parent): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if (! $user->isProfesseur() || ! $user->professeur || ! $parent instanceof Cours) {
            return false;
        }

        return $user->professeur->canAccessCours($parent);
    }
}
