<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Seul un admin peut gérer d'autres admins/directeurs (ADMIN-01 RG-1).
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() && ($target->isAdmin() || $target->isDirecteur());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() && ($target->isAdmin() || $target->isDirecteur());
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && ($target->isAdmin() || $target->isDirecteur());
    }
}
