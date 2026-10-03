<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

/** Cycle de vie des périodes d'une classe (CLS-02) : ajouter, changer de cours, supprimer, annuler. */
class ClassePeriodeService
{
    public function __construct(private readonly ClasseSessionGenerator $generator) {}

    /** @param array{periode_id: int, cours_id: int, date_premiere_session: string} $data */
    public function ajouter(Classe $classe, array $data): ClassePeriode
    {
        return $this->generator->ajouterPeriode($classe, $data);
    }

    /** @throws RegleMetierException 409 si des heures sont déjà encodées sur la période (RG-5) */
    public function changerCours(ClassePeriode $classePeriode, int $coursId, ?int $userId = null): ClassePeriode
    {
        $nb = $this->nbSaisies($classePeriode);
        if ($nb > 0) {
            throw RegleMetierException::conflit(
                "Le cours ne peut plus être changé : {$nb} saisie".($nb > 1 ? 's' : '')." d'heures ".($nb > 1 ? 'sont rattachées' : 'est rattachée').' à cette période.',
                ['nb_saisies' => $nb]
            );
        }

        return DB::transaction(function () use ($classePeriode, $coursId, $userId) {
            $ancien = $classePeriode->cours_id;
            if ($ancien !== $coursId) {
                $classePeriode->update(['cours_id' => $coursId]);
                $classePeriode->historiqueCours()->create([
                    'ancien_cours_id' => $ancien,
                    'nouveau_cours_id' => $coursId,
                    'user_id' => $userId,
                ]);
            }

            return $classePeriode->refresh();
        });
    }

    /**
     * Suppression physique : aucune heure encodée ET la classe a une autre période (RG-6).
     *
     * @throws RegleMetierException 409 sinon
     */
    public function supprimer(ClassePeriode $classePeriode): void
    {
        $nb = $this->nbSaisies($classePeriode);
        if ($nb > 0) {
            throw RegleMetierException::conflit(
                "Cette période a {$nb} saisie".($nb > 1 ? 's' : '')." d'heures : utilisez l'annulation (motif obligatoire) plutôt que la suppression.",
                ['nb_saisies' => $nb]
            );
        }

        if (ClassePeriode::where('classe_id', $classePeriode->classe_id)->count() < 2) {
            throw RegleMetierException::conflit('Une classe doit garder au moins une période : supprimez la classe plutôt que sa seule période.');
        }

        DB::transaction(function () use ($classePeriode) {
            // Les FK sont en restrict : on retire d'abord les sessions (bis remplaçants avant remplacés).
            CourseSession::where('classe_periode_id', $classePeriode->id)->whereNotNull('remplace_session_id')->delete();
            CourseSession::where('classe_periode_id', $classePeriode->id)->delete();
            $classePeriode->delete();
        });
    }

    /**
     * Annule la période : séances à venir (sans heures encodées) annulées avec le motif, statut `annulee`.
     *
     * @throws RegleMetierException 409 si la période est déjà annulée
     */
    public function annuler(ClassePeriode $classePeriode, string $motif): ClassePeriode
    {
        if ($classePeriode->isAnnulee()) {
            throw RegleMetierException::conflit('Cette période est déjà annulée.');
        }

        return DB::transaction(function () use ($classePeriode, $motif) {
            CourseSession::query()
                ->where('classe_periode_id', $classePeriode->id)
                ->whereIn('statut', [CourseSession::STATUT_PLANIFIEE, CourseSession::STATUT_EN_COURS])
                ->where('date', '>=', now('Europe/Brussels')->toDateString())
                ->whereNotIn('id', Timesheet::query()->whereNotNull('course_session_id')->select('course_session_id'))
                ->update([
                    'statut' => CourseSession::STATUT_ANNULEE,
                    'motif_annulation' => $motif,
                    'cancelled_at' => now(),
                ]);

            $classePeriode->update(['statut' => ClassePeriode::STATUT_ANNULEE, 'motif_annulation' => $motif]);

            return $classePeriode->refresh();
        });
    }

    public function nbSaisies(ClassePeriode $classePeriode): int
    {
        return Timesheet::whereIn('course_session_id', CourseSession::where('classe_periode_id', $classePeriode->id)->select('id'))->count();
    }
}
