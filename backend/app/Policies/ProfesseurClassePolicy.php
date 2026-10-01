<?php

namespace App\Policies;

use App\Models\User;

/** « Mes classes » : réservé au professeur connecté (ayant un profil professeur). */
class ProfesseurClassePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isProfesseur() && $user->professeur !== null;
    }
}
