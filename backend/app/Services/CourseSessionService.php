<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

/**
 * Ajustement des sessions d'une classe : déplacer, annuler (le numéro de séance est conservé),
 * ajouter un « bis » (RG-2). Jamais de renumérotation. Plus de blocage après la fin de période (CLS-02,
 * RG-4) : la séance est marquée `hors_periode` et la réponse porte un avertissement.
 */
class CourseSessionService
{
    public function __construct(private readonly ClasseProfesseurAssignmentService $assignations) {}

    /** @param array{date?: string, heure_debut?: string, heure_fin?: string, lieu?: ?string} $data */
    public function deplacer(CourseSession $session, array $data): CourseSession
    {
        $this->verifierDeplacable($session);

        return DB::transaction(function () use ($session, $data) {
            $session->update($data);
            $this->reporterHeuresBrouillon($session->id, $session->date->toDateString());

            return $session->refresh();
        });
    }

    /**
     * Une séance, même passée, se déplace tant qu'aucune heure n'y est soumise ou validée (CLS-06).
     *
     * @throws RegleMetierException 409 si la session est annulée ou a des heures soumises ou validées
     */
    public function verifierDeplacable(CourseSession $session): void
    {
        if ($session->isAnnulee()) {
            throw RegleMetierException::conflit('Une session annulée ne peut pas être déplacée.');
        }
        $nb = $session->timesheets()->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)->count();
        if ($nb > 0) {
            throw RegleMetierException::conflit(
                "Cette session ne peut pas être déplacée : {$nb} saisie".($nb > 1 ? 's' : '')." d'heures ".($nb > 1 ? 'soumises ou validées y sont rattachées' : 'soumise ou validée y est rattachée').'. Le remplacement d\'un professeur reste possible.'
            );
        }
    }

    /** Les heures encore en brouillon suivent la nouvelle date de leur séance. */
    public function reporterHeuresBrouillon(int $sessionId, string $date): void
    {
        Timesheet::where('course_session_id', $sessionId)
            ->where('statut_validation', Timesheet::STATUT_BROUILLON)
            ->update(['date_prestation' => $date]);
    }

    public function annuler(CourseSession $session, string $motif): CourseSession
    {
        if (! $session->isCancellable()) {
            throw RegleMetierException::conflit('Seule une session planifiée ou en cours peut être annulée.');
        }
        $this->refuserSiHeuresEncodees($session, 'annulée');

        $session->update([
            'statut' => CourseSession::STATUT_ANNULEE,
            'motif_annulation' => $motif,
            'cancelled_at' => now(),
        ]);

        return $session->refresh();
    }

    /**
     * R-T3-8 / AC-33 : une session qui a des heures encodées ne peut être ni annulée ni déplacée (le remplacement
     * d'un professeur, lui, reste possible).
     */
    private function refuserSiHeuresEncodees(CourseSession $session, string $action): void
    {
        $nb = $session->timesheets()->count();
        if ($nb > 0) {
            throw RegleMetierException::conflit(
                "Cette session ne peut pas être {$action} : {$nb} saisie".($nb > 1 ? 's' : '')." d'heures y ".($nb > 1 ? 'sont rattachées' : 'est rattachée').'. Le remplacement d\'un professeur reste possible.'
            );
        }
    }

    /**
     * Crée un « bis » rattaché à l'une des séances d'une période de la classe.
     *
     * @param  array{seance_numero: int, date: string, periode_numero?: ?int, classe_periode_id?: ?int, heure_debut?: ?string, heure_fin?: ?string, lieu?: ?string}  $data
     *
     * @throws RegleMetierException 422 période/séance inconnue ; 409 dépassement non confirmé
     */
    public function ajouterBis(Classe $classe, array $data, bool $confirmerDepassement = false): CourseSession
    {
        $classePeriode = $this->periodeCible($classe, $data);

        return DB::transaction(function () use ($classe, $classePeriode, $data, $confirmerDepassement) {
            $seance = CourseSession::query()
                ->where('classe_periode_id', $classePeriode->id)
                ->where('seance_numero', $data['seance_numero'])
                ->orderByDesc('bis_rang')
                ->lockForUpdate()
                ->get();

            if ($seance->isEmpty()) {
                $message = "La séance {$data['seance_numero']} n'existe pas dans cette période : un bis doit se rattacher à l'une de ses séances.";
                throw RegleMetierException::invalide($message, ['seance_numero' => [$message]]);
            }

            $nbActives = CourseSession::where('classe_periode_id', $classePeriode->id)
                ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
                ->count();

            if ($nbActives + 1 > ClasseSessionGenerator::NB_SEANCES && ! $confirmerDepassement) {
                $nb = $nbActives + 1;
                throw RegleMetierException::conflit(
                    "Cette période passera à {$nb} sessions",
                    ['nb_sessions' => $nb]
                );
            }

            // Dernière session annulée de la séance qui n'a pas encore de remplaçante.
            $dejaRemplacees = $seance->pluck('remplace_session_id')->filter()->all();
            $remplacee = $seance->first(
                fn (CourseSession $s) => $s->isAnnulee() && ! in_array($s->id, $dejaRemplacees, true)
            );

            $bis = CourseSession::create([
                'classe_id' => $classe->id,
                'classe_periode_id' => $classePeriode->id,
                'seance_numero' => $data['seance_numero'],
                'bis_rang' => $seance->max('bis_rang') + 1,
                'remplace_session_id' => $remplacee?->id,
                'date' => $data['date'],
                'heure_debut' => $data['heure_debut'] ?? $classe->heure_debut,
                'heure_fin' => $data['heure_fin'] ?? $classe->heure_fin,
                'lieu' => array_key_exists('lieu', $data) ? $data['lieu'] : $classe->lieu,
                'statut' => CourseSession::STATUT_PLANIFIEE,
            ])->refresh();

            // Les professeurs actifs de la classe à la date du bis y sont propagés.
            $this->assignations->propagerAuxSessions($classe, [$bis]);

            return $bis;
        });
    }

    /** Période visée par un bis : `classe_periode_id`, sinon `periode_numero`, sinon P1 (ou l'unique période de la classe). */
    private function periodeCible(Classe $classe, array $data): ClassePeriode
    {
        $periodes = $classe->periodes()->with('periode')->get();

        if (! empty($data['classe_periode_id'])) {
            $cible = $periodes->firstWhere('id', (int) $data['classe_periode_id']);
        } elseif (! empty($data['periode_numero'])) {
            $cible = $periodes->first(fn (ClassePeriode $p) => $p->periode->numero === (int) $data['periode_numero']);
        } else {
            $cible = $periodes->count() === 1 ? $periodes->first() : $periodes->first(fn (ClassePeriode $p) => $p->periode->numero === 1);
        }

        if (! $cible) {
            $message = "Cette classe n'a pas cette période.";
            throw RegleMetierException::invalide($message, ['periode_numero' => [$message]]);
        }

        return $cible;
    }
}
