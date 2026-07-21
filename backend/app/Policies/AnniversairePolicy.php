<?php

namespace App\Policies;

use App\Models\Anniversaire;
use App\Models\User;

class AnniversairePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Anniversaire $anniversaire): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Anniversaire $anniversaire): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Anniversaire $anniversaire): bool
    {
        return $user->isStaff();
    }
}
