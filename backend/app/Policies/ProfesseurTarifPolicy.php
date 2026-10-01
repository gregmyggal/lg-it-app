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

    // Admin et directeur gèrent les tarifs (création, modification, suppression)
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, ProfesseurTarif $tarif): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, ProfesseurTarif $tarif): bool
    {
        return $user->isStaff();
    }
}
