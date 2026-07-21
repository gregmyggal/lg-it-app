<?php

namespace App\Policies;

use App\Models\Stage;
use App\Models\User;

class StagePolicy
{
    // Pas d'isolation par professeur pour les stages (aucune taxonomie équivalente en WP) :
    // lecture libre pour tout utilisateur authentifié (le catalogue public passe par les routes
    // /api/public/* non authentifiées, filtrées sur statut=publish côté contrôleur), mutation staff only.
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Stage $stage): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Stage $stage): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Stage $stage): bool
    {
        return $user->isStaff();
    }
}
