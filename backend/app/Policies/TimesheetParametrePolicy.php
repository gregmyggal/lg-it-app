<?php

namespace App\Policies;

use App\Models\TimesheetParametre;
use App\Models\User;

/** TS-00 : directeur et admin uniquement. */
class TimesheetParametrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ?TimesheetParametre $parametre = null): bool
    {
        return $user->isStaff();
    }
}
