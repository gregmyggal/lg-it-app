<?php

namespace App\Policies;

use App\Models\Professeur;
use App\Models\User;

class ProfesseurPolicy
{
    // Données RH (contrat, statut, dates) — réservées au staff, comme le CPT `professeur`
    // en WP (show_in_rest=false). Un professeur consulte son propre profil via GET /api/me.
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Professeur $professeur): bool
    {
        return $user->isStaff() || $professeur->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Professeur $professeur): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Professeur $professeur): bool
    {
        return $user->isStaff();
    }
}
