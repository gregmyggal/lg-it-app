<?php

namespace App\Policies;

use App\Models\Cours;
use App\Models\CoursRessource;
use App\Models\User;

class CoursRessourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isProfesseur();
    }

    public function view(User $user, CoursRessource $coursRessource): bool
    {
        return $this->canAccessParentCours($user, $coursRessource->cours);
    }

    // Le cours parent n'existe pas encore côté modèle : on vérifie l'accès sur le cours cible.
    public function create(User $user, Cours $cours): bool
    {
        return $this->canAccessParentCours($user, $cours);
    }

    public function update(User $user, CoursRessource $coursRessource): bool
    {
        return $this->canAccessParentCours($user, $coursRessource->cours);
    }

    public function delete(User $user, CoursRessource $coursRessource): bool
    {
        return $this->canAccessParentCours($user, $coursRessource->cours);
    }

    private function canAccessParentCours(User $user, Cours $cours): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->professeur && $user->professeur->canAccessCours($cours);
    }
}
