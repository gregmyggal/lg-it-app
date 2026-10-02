<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Saisie des heures (CLS-01 T3) : création liée à une session ou libre, écran mensuel, soumission du mois.
 * Une timesheet est toujours celle d'UN professeur (RG-5) ; remplacer un professeur ne la touche jamais (RG-9).
 */
class TimesheetService
{
    public const TYPE_ANIMATION = 'animation';

    public const TYPE_PREPARATION = 'preparation';

    public const TYPE_COURS = 'cours';

    /** Frais de déplacement : « nombre » = nombre de déplacements, montant forfaitaire par année (paramètres). */
    public const TYPE_DEPLACEMENT = 'deplacement';

    public const TYPES = [self::TYPE_ANIMATION, self::TYPE_COURS, self::TYPE_PREPARATION, self::TYPE_DEPLACEMENT];

    /**
     * Crée une saisie pour le professeur connecté.
     *
     * @param  array<string, mixed>  $data  session : course_session_id (+ type_activite, nombre_heures, commentaire) ;
     *                                      libre : date_prestation, type_activite, nombre_heures (+ cours_id, commentaire)
     *
     * @throws RegleMetierException 422 session non commencée/annulée ou doublon
     */
    public function creer(User $user, array $data): Timesheet
    {
        $professeur = $user->professeur;
        $type = $data['type_activite'] ?? self::TYPE_ANIMATION;

        if (empty($data['course_session_id'])) {
            return Timesheet::create([
                'professeur_id' => $professeur->id,
                'date_prestation' => $data['date_prestation'],
                'type_activite' => $type,
                'nombre_heures' => $data['nombre_heures'],
                'cours_id' => $data['cours_id'] ?? null,
                'commentaire' => $data['commentaire'] ?? null,
                'statut_validation' => Timesheet::STATUT_BROUILLON,
            ]);
        }

        $session = CourseSession::with('classe')->findOrFail($data['course_session_id']);
        Gate::forUser($user)->authorize('createForSession', [Timesheet::class, $session]);

        return DB::transaction(function () use ($professeur, $session, $type, $data) {
            if ($session->isAnnulee()) {
                throw RegleMetierException::invalide('Cette session est annulée : aucune heure ne peut y être encodée.', ['course_session_id' => ['Session annulée.']]);
            }
            if (! $this->aCommence($session)) {
                throw RegleMetierException::invalide('Cette session n\'a pas encore commencé : vous pourrez encoder vos heures dès son début.', ['course_session_id' => ['Session non commencée.']]);
            }
            if (Timesheet::where('professeur_id', $professeur->id)->where('course_session_id', $session->id)->where('type_activite', $type)->lockForUpdate()->exists()) {
                throw RegleMetierException::invalide('Ces heures sont déjà encodées pour cette session.', ['course_session_id' => ['Doublon (professeur, session, type d\'activité).']]);
            }

            return Timesheet::create([
                'professeur_id' => $professeur->id,
                'course_session_id' => $session->id,
                'date_prestation' => $session->date->toDateString(),
                'cours_id' => $session->classe->cours_id,
                'type_activite' => $type,
                'nombre_heures' => $data['nombre_heures'] ?? $this->dureeParDefaut($session),
                'commentaire' => $data['commentaire'] ?? null,
                'statut_validation' => Timesheet::STATUT_BROUILLON,
            ]);
        });
    }

    /** Une session a commencé : date passée, ou aujourd'hui à partir de son heure de début (Europe/Brussels). */
    public function aCommence(CourseSession $session): bool
    {
        $maintenant = Carbon::now('Europe/Brussels');
        $date = $session->date->toDateString();

        return $date < $maintenant->toDateString()
            || ($date === $maintenant->toDateString() && substr($session->heure_debut, 0, 5) <= $maintenant->format('H:i'));
    }

    /** Durée de la session en heures (arrondie au quart d'heure), bornée à 0,5 – 24 h. */
    public function dureeParDefaut(CourseSession $session): float
    {
        [$h1, $m1] = array_map('intval', explode(':', $session->heure_debut));
        [$h2, $m2] = array_map('intval', explode(':', $session->heure_fin));
        $heures = round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60 * 4) / 4;

        return max(0.5, min(24.0, $heures));
    }

    /** Le professeur peut-il encoder cette session maintenant ? (assigné non remplacé ou remplaçant, commencée, non annulée) */
    public function peutEncoder(Professeur $professeur, CourseSession $session): bool
    {
        return ! $session->isAnnulee()
            && $this->aCommence($session)
            && SessionProfesseur::where('course_session_id', $session->id)
                ->where('professeur_id', $professeur->id)->where('remplace', false)->exists();
    }

    /**
     * État d'encodage d'une session pour un professeur, calculé (pas un statut) :
     * a_encoder | brouillon | soumis | confirme | genere. Plusieurs saisies : l'état le moins avancé l'emporte.
     *
     * @param  iterable<Timesheet>  $saisies
     */
    public function etatEncodage(iterable $saisies): string
    {
        $ordre = ['brouillon' => 0, 'soumis' => 1, 'confirme' => 2, 'genere' => 3];
        $etat = null;
        foreach ($saisies as $t) {
            if ($etat === null || ($ordre[$t->statut_validation] ?? 0) < $ordre[$etat]) {
                $etat = $t->statut_validation;
            }
        }

        return $etat ?? 'a_encoder';
    }

    /**
     * Soumet le mois : crée d'abord les saisies préremplies des sessions incluses, puis passe en « soumis » tous les
     * brouillons du mois — en une transaction (R-T3-11).
     *
     * @param  list<array{course_session_id: int, nombre_heures?: float, type_activite?: string}>  $sessions
     * @return array{creees: int, soumises: int}
     */
    public function soumettreMois(User $user, int $annee, int $mois, array $sessions = []): array
    {
        return DB::transaction(function () use ($user, $annee, $mois, $sessions) {
            $creees = 0;
            foreach ($sessions as $s) {
                $session = CourseSession::findOrFail($s['course_session_id']);
                if ($session->date->year !== $annee || $session->date->month !== $mois) {
                    throw RegleMetierException::invalide('Une session incluse n\'appartient pas au mois soumis.', ['sessions' => ['Session hors du mois.']]);
                }
                $dejaEncodee = Timesheet::where('professeur_id', $user->professeur->id)
                    ->where('course_session_id', $session->id)
                    ->where('type_activite', $s['type_activite'] ?? self::TYPE_ANIMATION)->exists();
                if ($dejaEncodee) {
                    continue; // idempotent : déjà saisie, elle sera soumise avec les autres brouillons
                }
                $this->creer($user, $s);
                $creees++;
            }

            $soumises = Timesheet::where('professeur_id', $user->professeur->id)
                ->where('statut_validation', Timesheet::STATUT_BROUILLON)
                ->whereYear('date_prestation', $annee)
                ->whereMonth('date_prestation', $mois)
                ->update(['statut_validation' => Timesheet::STATUT_SOUMIS]);

            return ['creees' => $creees, 'soumises' => $soumises];
        });
    }
}
