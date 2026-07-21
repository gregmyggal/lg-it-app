<?php

namespace App\Policies;

use App\Models\Cours;
use App\Models\User;

class CoursPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isProfesseur();
    }

    public function view(User $user, Cours $cours): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->professeur && $user->professeur->canAccessCours($cours);
    }

    // Directeur/admin uniquement : un professeur ne modifie jamais la structure
    // d'un cours (titre/contenu), seulement ses ressources — cf. CoursRessourcePolicy.
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Cours $cours): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Cours $cours): bool
    {
        return $user->isStaff();
    }
}
