<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

/**
 * Écriture : admin et directeur uniquement. Lecture : staff, ou professeur limité (par scope de requête
 * Classe::visiblePour) aux classes où il a (ou a eu) une assignation.
 */
class ClassePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || ($user->isProfesseur() && $user->professeur !== null);
    }

    public function view(User $user, Classe $model): bool
    {
        return $user->isStaff()
            || Classe::visiblePour($user)->whereKey($model->id)->exists();
    }

    /** Assigner / modifier / terminer un professeur sur la classe (staff). */
    public function manageProfesseurs(User $user, ?Classe $model = null): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Classe $model): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Classe $model): bool
    {
        return $user->isStaff();
    }
}
