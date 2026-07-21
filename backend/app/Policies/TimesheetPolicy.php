<?php

namespace App\Policies;

use App\Models\Timesheet;
use App\Models\User;

class TimesheetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() || $user->isProfesseur();
    }

    public function view(User $user, Timesheet $timesheet): bool
    {
        return $user->isStaff() || $this->isOwner($user, $timesheet);
    }

    // Un professeur ne crée une saisie que pour lui-même ; $professeurId vient du payload de la requête.
    public function create(User $user, ?int $professeurId = null): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->isProfesseur()
            && $user->professeur
            && $professeurId === $user->professeur->id;
    }

    // Verrou (US-302/311) : seul l'admin peut modifier une saisie soumise/validée ;
    // le professeur ne modifie que ses brouillons. Le directeur ne passe que par validate().
    public function update(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->isOwner($user, $timesheet) && ! $timesheet->isLocked();
    }

    public function delete(User $user, Timesheet $timesheet): bool
    {
        return $this->update($user, $timesheet);
    }

    // Transition brouillon → soumis, réservée au professeur propriétaire.
    public function submit(User $user, Timesheet $timesheet): bool
    {
        return $this->isOwner($user, $timesheet) && $timesheet->statut_validation === 'brouillon';
    }

    // Transition soumis → validé, réservée au directeur/admin.
    public function validateEntry(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDirecteur() && $timesheet->statut_validation === 'soumis';
    }

    private function isOwner(User $user, Timesheet $timesheet): bool
    {
        return $user->isProfesseur()
            && $user->professeur
            && $timesheet->professeur_id === $user->professeur->id;
    }
}
