<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

/**
 * T1 : admin et directeur uniquement (un professeur reçoit 403 ; son accès arrive en T2
 * via professeur_classe).
 */
class ClassePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Classe $model): bool
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
