<?php

namespace App\Policies;

use App\Models\CourseSession;
use App\Models\SessionProfesseur;
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

    // CLS-01 T3 (Q11) : seuls les professeurs encodent leurs heures ; le staff n'encode pas pour eux.
    public function create(User $user): bool
    {
        return $user->isProfesseur() && $user->professeur !== null;
    }

    // R-T3-2 : le professeur assigné à la session (non remplacé) ou son remplaçant peut y rattacher des heures ;
    // le remplacé ne peut plus créer de saisie (il garde les siennes). Le rôle n'a aucun effet.
    public function createForSession(User $user, CourseSession $session): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        return SessionProfesseur::where('course_session_id', $session->id)
            ->where('professeur_id', $user->professeur->id)
            ->where('remplace', false)
            ->exists();
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
        return $this->isOwner($user, $timesheet) && $timesheet->statut_validation === Timesheet::STATUT_BROUILLON;
    }

    // Transition soumis → validé, réservée au directeur/admin.
    public function validateEntry(User $user, Timesheet $timesheet): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isDirecteur() && $timesheet->statut_validation === Timesheet::STATUT_SOUMIS;
    }

    private function isOwner(User $user, Timesheet $timesheet): bool
    {
        return $user->isProfesseur()
            && $user->professeur
            && $timesheet->professeur_id === $user->professeur->id;
    }
}
