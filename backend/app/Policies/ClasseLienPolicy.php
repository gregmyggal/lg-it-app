<?php

namespace App\Policies;

use App\Models\ClasseLien;
use App\Models\Cours;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ClasseLienPolicy
{
    public function viewAny(User $user, Model $parent): bool
    {
        return $this->canAccessParent($user, $parent);
    }

    public function view(User $user, ClasseLien $classeLien): bool
    {
        return $this->canAccessParent($user, $classeLien->parent);
    }

    public function create(User $user, Model $parent): bool
    {
        return $this->canAccessParent($user, $parent);
    }

    public function update(User $user, ClasseLien $classeLien): bool
    {
        return $this->canAccessParent($user, $classeLien->parent);
    }

    public function delete(User $user, ClasseLien $classeLien): bool
    {
        return $this->canAccessParent($user, $classeLien->parent);
    }

    // Portage de LGIT_Prof_Classe_Links::verify_activity_access : un professeur ne gère
    // les liens de classe que sur un cours qui lui est assigné (stage/formation/anniversaire
    // n'ont pas de notion de type_cours → toujours refusé au professeur, comme dans WP).
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
