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
 * Saisie des heures (CLS-01 T3) : création liée à une session ou libre, écran mensuel, soumission du mois entier.
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
     * @throws RegleMetierException 422 session annulée, date au-delà du mois courant, mois soumis ou doublon
     */
    public function creer(User $user, array $data): Timesheet
    {
        $professeur = $user->professeur;
        $type = $data['type_activite'] ?? self::TYPE_ANIMATION;

        if (empty($data['course_session_id'])) {
            $this->verifierDansMoisCourant(Carbon::parse($data['date_prestation']));
            $this->verifierMoisNonSoumis($professeur->id, Carbon::parse($data['date_prestation']));

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

        $session = CourseSession::with('classe', 'classePeriode')->findOrFail($data['course_session_id']);
        Gate::forUser($user)->authorize('createForSession', [Timesheet::class, $session]);

        return DB::transaction(function () use ($professeur, $session, $type, $data) {
            if ($session->isAnnulee()) {
                throw RegleMetierException::invalide('Cette session est annulée : aucune heure ne peut y être encodée.', ['course_session_id' => ['Session annulée.']]);
            }
            $this->verifierDansMoisCourant($session->date);
            $this->verifierMoisNonSoumis($professeur->id, $session->date);
            if (Timesheet::where('professeur_id', $professeur->id)->where('course_session_id', $session->id)->where('type_activite', $type)->lockForUpdate()->exists()) {
                throw RegleMetierException::invalide('Ces heures sont déjà encodées pour cette session.', ['course_session_id' => ['Doublon (professeur, session, type d\'activité).']]);
            }

            return Timesheet::create([
                'professeur_id' => $professeur->id,
                'course_session_id' => $session->id,
                'date_prestation' => $session->date->toDateString(),
                'cours_id' => $session->classePeriode->cours_id,
                'type_activite' => $type,
                'nombre_heures' => $data['nombre_heures'] ?? $this->dureeParDefaut($session),
                'commentaire' => $data['commentaire'] ?? null,
                'statut_validation' => Timesheet::STATUT_BROUILLON,
            ]);
        });
    }

    /**
     * Le professeur encode librement dans le futur tant que la date reste dans le mois courant (Europe/Brussels) :
     * il peut ainsi clôturer sa timesheet avant la fin du mois, le paiement se faisant sur base du document.
     *
     * @throws RegleMetierException 422 si la date est postérieure à la fin du mois courant
     */
    private function verifierDansMoisCourant(Carbon $date): void
    {
        if ($date->toDateString() > Carbon::now('Europe/Brussels')->endOfMonth()->toDateString()) {
            throw RegleMetierException::invalide(
                'Les heures ne peuvent être encodées que jusqu\'à la fin du mois en cours.',
                ['date_prestation' => ['Date postérieure au mois en cours.']]
            );
        }
    }

    /** Un mois se soumet une seule fois : après soumission, plus aucune saisie dans ce mois (sauf remise en brouillon par la direction). */
    public function moisSoumis(int $professeurId, int $annee, int $mois): bool
    {
        return Timesheet::where('professeur_id', $professeurId)
            ->whereYear('date_prestation', $annee)->whereMonth('date_prestation', $mois)
            ->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)
            ->exists();
    }

    /** @throws RegleMetierException 422 si le mois de la date est déjà soumis */
    private function verifierMoisNonSoumis(int $professeurId, Carbon $date): void
    {
        if ($this->moisSoumis($professeurId, $date->year, $date->month)) {
            throw RegleMetierException::invalide(
                'Ce mois est déjà soumis : il ne peut être soumis qu\'une fois. Toute correction passe par la direction.',
                ['date_prestation' => ['Mois déjà soumis.']]
            );
        }
    }

    /** Une séance à venir (pas encore commencée) peut être encodée à l'avance (TS-03). */
    public function estAVenir(CourseSession $session): bool
    {
        return ! $this->aCommence($session);
    }

    /** Une session a commencé : date passée, ou aujourd'hui à partir de son heure de début (Europe/Brussels). */
    public function aCommence(CourseSession $session): bool
    {
        $maintenant = Carbon::now('Europe/Brussels');
        $date = $session->date->toDateString();

        return $date < $maintenant->toDateString()
            || ($date === $maintenant->toDateString() && substr($session->heure_debut, 0, 5) <= $maintenant->format('H:i'));
    }

    /**
     * Heures proposées pour une session : les heures DÉFRAYABLES (cours + préparation), distinctes de la durée du
     * calendrier. Résolution : valeur du cours (DEF-01 T2) puis défaut global de l'année de la séance (DEF-01 T1).
     * N'affecte jamais une saisie déjà encodée : seules les séances non encodées sont proposées avec cette valeur.
     */
    public function dureeParDefaut(CourseSession $session): float
    {
        return $this->heuresDefrayables($session)['valeur'];
    }

    /** @return array{valeur: float, source: string} heures défrayables de la séance et origine de la valeur */
    public function heuresDefrayables(CourseSession $session): array
    {
        $cours = $session->loadMissing('classePeriode.cours')->classePeriode?->cours;

        return app(TimesheetParametreService::class)->heuresDefrayablesPour($cours, (int) $session->date->format('Y'));
    }

    /** Durée de la séance au calendrier, en heures (arrondie au quart d'heure, bornée à 0,5 – 24 h) : information seulement. */
    public function dureeSeance(CourseSession $session): float
    {
        return self::dureeEntre($session->heure_debut, $session->heure_fin);
    }

    public static function dureeEntre(string $debut, string $fin): float
    {
        [$h1, $m1] = array_map('intval', explode(':', $debut));
        [$h2, $m2] = array_map('intval', explode(':', $fin));
        $heures = round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60 * 4) / 4;

        return max(0.5, min(24.0, $heures));
    }

    /** Le professeur peut-il encoder cette session ? (assigné non remplacé ou remplaçant, non annulée ; passée ou à venir, TS-03) */
    public function peutEncoder(Professeur $professeur, CourseSession $session): bool
    {
        return ! $session->isAnnulee()
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
     * Soumet le mois entier : toutes les heures encodées (brouillons) du mois passent en « soumis », en une transaction.
     * Rien n'est coché ni choisi par le professeur ; il n'existe pas de soumission ligne par ligne.
     *
     * @return array{soumises: int}
     *
     * @throws RegleMetierException 422 mois déjà soumis, ou aucune heure à soumettre
     */
    public function soumettreMois(User $user, int $annee, int $mois): array
    {
        $professeurId = $user->professeur->id;
        if ($this->moisSoumis($professeurId, $annee, $mois)) {
            throw RegleMetierException::invalide('Ce mois est déjà soumis : il ne peut être soumis qu\'une fois.', ['mois' => ['Mois déjà soumis.']]);
        }

        return DB::transaction(function () use ($professeurId, $annee, $mois) {
            $soumises = Timesheet::where('professeur_id', $professeurId)
                ->where('statut_validation', Timesheet::STATUT_BROUILLON)
                ->whereYear('date_prestation', $annee)
                ->whereMonth('date_prestation', $mois)
                ->update(['statut_validation' => Timesheet::STATUT_SOUMIS]);

            if ($soumises === 0) {
                throw RegleMetierException::invalide('Aucune heure encodée à soumettre pour ce mois.', ['mois' => ['Aucune heure à soumettre.']]);
            }

            return ['soumises' => $soumises];
        });
    }
}
