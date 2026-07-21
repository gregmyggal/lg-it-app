<?php

namespace App\Policies;

use App\Models\TypeFormation;
use App\Models\User;

class TypeFormationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TypeFormation $typeFormation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, TypeFormation $typeFormation): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, TypeFormation $typeFormation): bool
    {
        return $user->isStaff();
    }
}
