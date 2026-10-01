<?php

namespace App\Policies;

use App\Models\CourseSession;
use App\Models\User;

/**
 * Écriture : admin et directeur uniquement. Lecture : staff, ou professeur limité (par scope de requête
 * CourseSession::visiblePour) à ses sessions.
 */
class CourseSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || ($user->isProfesseur() && $user->professeur !== null);
    }

    public function view(User $user, CourseSession $model): bool
    {
        return $user->isStaff()
            || CourseSession::visiblePour($user)->whereKey($model->id)->exists();
    }

    /** Remplacer un professeur / annuler un remplacement (staff, y compris sur session passée). */
    public function replace(User $user, CourseSession $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, CourseSession $model): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, CourseSession $model): bool
    {
        return $user->isStaff();
    }

    public function cancel(User $user, CourseSession $model): bool
    {
        return $user->isStaff();
    }
}
