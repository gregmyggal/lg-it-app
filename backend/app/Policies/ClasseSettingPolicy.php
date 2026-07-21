<?php

namespace App\Policies;

use App\Models\ClasseSetting;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClasseSettingPolicy
{
    public function view(User $user, ClasseSetting $classeSetting): bool
    {
        return $this->canAccessParent($user, $classeSetting->parent);
    }

    public function update(User $user, ClasseSetting $classeSetting): bool
    {
        return $this->canAccessParent($user, $classeSetting->parent);
    }

    public function createFor(User $user, Model $parent): bool
    {
        return $this->canAccessParent($user, $parent);
    }

    // Même règle que ClasseLienPolicy : professeur limité aux cours qui lui sont assignés.
    private function canAccessParent(User $user, ?Model $parent): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if (! $user->isProfesseur() || ! $user->professeur || ! $parent instanceof Cours) {
            return false;
        }

        return $user->professeur->canAccessCours($parent);
    }
}
