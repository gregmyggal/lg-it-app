<?php

namespace App\Policies;

use App\Models\Employeur;
use App\Models\User;

/** EMP-01 : entités employeurs — lecture pour le staff, gestion réservée à l'admin. */
class EmployeurPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Employeur $employeur): bool
    {
        return $user->isAdmin();
    }
}
