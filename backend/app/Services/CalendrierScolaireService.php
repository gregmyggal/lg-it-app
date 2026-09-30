<?php

namespace App\Services;

use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\CourseSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Calendrier scolaire d'une année : lecture (entrées actives), alertes sur sessions,
 * et règles d'édition FWB / école. Aucune session n'est jamais déplacée automatiquement.
 */
class CalendrierScolaireService
{
    /** @return Collection<int, CalendrierScolaire> entrées non masquées de l'année */
    public function entreesActives(int $anneeScolaireId): Collection
    {
        return CalendrierScolaire::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->actives()
            ->orderBy('date_debut')
            ->get();
    }

    /** @param  Collection<int, CalendrierScolaire>  $entrees */
    public function entreeCouvrant(Collection $entrees, string $date): ?CalendrierScolaire
    {
        return $entrees->first(
            fn (CalendrierScolaire $e) => $e->date_debut->toDateString() <= $date
                && $e->date_fin->toDateString() >= $date
        );
    }

    /**
     * Renseigne `alerte_calendrier` ({libelle, type} ou null) sur les sessions données,
     * avec une seule requête par année scolaire (pas de N+1).
     *
     * @param  iterable<CourseSession>  $sessions
     */
    public function attachAlerts(iterable $sessions): void
    {
        $sessions = $sessions instanceof EloquentCollection
            ? $sessions
            : new EloquentCollection(is_array($sessions) ? $sessions : iterator_to_array($sessions));
        $sessions->loadMissing('classe:id,annee_scolaire_id');

        $entreesParAnnee = [];
        foreach ($sessions as $session) {
            $anneeId = $session->classe->annee_scolaire_id;
            $entreesParAnnee[$anneeId] ??= $this->entreesActives($anneeId);

            $entree = $session->isActive() && $session->statut !== CourseSession::STATUT_TERMINEE
                ? $this->entreeCouvrant($entreesParAnnee[$anneeId], $session->date->toDateString())
                : null;

            $session->alerte_calendrier = $entree
                ? ['libelle' => $entree->libelle, 'type' => $entree->type]
                : null;
        }
    }

    /** @param array{date_debut: string, date_fin: string, type: string, libelle: string} $data */
    public function creer(AnneeScolaire $annee, array $data): CalendrierScolaire
    {
        return $annee->calendrier()->create($data + [
            'source' => CalendrierScolaire::SOURCE_ECOLE,
            'masque' => false,
        ]);
    }

    /** Une entrée FWB modifiée à la main n'est plus touchée par les imports. */
    public function modifier(CalendrierScolaire $entree, array $data): CalendrierScolaire
    {
        if ($entree->isFwb()) {
            $data['modifie_manuellement'] = true;
        }

        $entree->update($data);

        return $entree->refresh();
    }

    /**
     * Suppression : entrée FWB → masquée (reste masquée aux imports suivants) ;
     * entrée école → supprimée physiquement.
     * (Proposition UX non encore tranchée : règle isolée ici.)
     *
     * @return bool true si l'entrée a été masquée, false si supprimée
     */
    public function supprimer(CalendrierScolaire $entree): bool
    {
        if ($entree->isFwb()) {
            $entree->update(['masque' => true]);

            return true;
        }

        $entree->delete();

        return false;
    }
}
