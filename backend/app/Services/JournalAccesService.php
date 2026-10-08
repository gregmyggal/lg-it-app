<?php

namespace App\Services;

use App\Models\AccesDonneeSensible;
use App\Models\Professeur;
use App\Models\User;

/**
 * RGPD-01 : alimente le journal des accès aux données sensibles (ids uniquement).
 * La lecture par le titulaire de ses propres données n'est pas journalisée (aucun risque d'abus interne).
 */
class JournalAccesService
{
    public function enregistrer(User $acteur, Professeur $professeur, string $action): void
    {
        if ($professeur->user_id === $acteur->id) {
            return;
        }

        AccesDonneeSensible::create(['user_id' => $acteur->id, 'professeur_id' => $professeur->id, 'action' => $action]);
    }

    /** Purge les traces plus anciennes que la rétention (requête de masse : seule voie de suppression). */
    public function purger(): int
    {
        return AccesDonneeSensible::query()->where('created_at', '<', now()->subMonths(AccesDonneeSensible::RETENTION_MOIS))->delete();
    }
}
