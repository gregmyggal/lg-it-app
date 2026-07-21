<?php

namespace App\Policies;

use App\Models\TypeCours;
use App\Models\User;

class TypeCoursPolicy
{
    // Lecture ouverte à tout utilisateur authentifié (listes déroulantes de filtres, assignation).
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TypeCours $typeCours): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, TypeCours $typeCours): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, TypeCours $typeCours): bool
    {
        return $user->isStaff();
    }
}
