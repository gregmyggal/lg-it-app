<?php

namespace App\Policies;

use App\Models\ProfesseurTarif;
use App\Models\User;

class ProfesseurTarifPolicy
{
    public function viewAny(User $user): bool
    {
        // Seul admin et directeur voient les tarifs
        return $user->isStaff();
    }

    public function view(User $user, ProfesseurTarif $tarif): bool
    {
        return $user->isStaff();
    }

    // Seul admin peut créer/modifier des tarifs
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProfesseurTarif $tarif): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProfesseurTarif $tarif): bool
    {
        return $user->isAdmin();
    }
}
