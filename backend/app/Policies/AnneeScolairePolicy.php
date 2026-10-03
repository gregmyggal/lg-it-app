<?php

namespace App\Policies;

use App\Models\AnneeScolaire;
use App\Models\User;

/**
 * T1 : admin et directeur uniquement (un professeur reçoit 403 ; son accès arrive en T2
 * via professeur_classe).
 */
class AnneeScolairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, AnneeScolaire $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, AnneeScolaire $model): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, AnneeScolaire $model): bool
    {
        return $user->isStaff();
    }

    /** Archiver / réactiver une année (CLS-03). */
    public function archiver(User $user, AnneeScolaire $model): bool
    {
        return $user->isStaff();
    }
}
