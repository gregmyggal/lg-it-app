<?php

namespace App\Policies;

use App\Models\CalendrierScolaire;
use App\Models\User;

/**
 * T1 : admin et directeur uniquement (un professeur reçoit 403 ; son accès arrive en T2
 * via professeur_classe).
 */
class CalendrierScolairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, CalendrierScolaire $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, CalendrierScolaire $model): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, CalendrierScolaire $model): bool
    {
        return $user->isStaff();
    }

    /** Import du calendrier FWB : administrateur uniquement. */
    public function importFwb(User $user): bool
    {
        return $user->isAdmin();
    }
}
