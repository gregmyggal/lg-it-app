<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * PROF-01 : cycle de vie du professeur et de son compte (création, désactivation, réactivation, envoi de lien,
 * email de connexion). ADMIN-03 : aucun mot de passe n'est généré ni communiqué ; l'accès passe par un lien à usage
 * unique envoyé à l'email de connexion (AccesCompteService, commun à tous les rôles).
 */
class ProfesseurCompteService
{
    public function __construct(
        private readonly ClasseProfesseurAssignmentService $assignations,
        private readonly AccesCompteService $acces,
    ) {}

    /** @return array{professeur: Professeur, mail_envoye: bool, lien: ?string} */
    public function creer(array $data, bool $envoyerInvitation = true): array
    {
        $professeur = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['prenom'].' '.$data['nom'],
                'email' => $data['login_email'],
                'password' => $this->acces->motDePasseInutilisable(),
                'role' => 'professeur',
            ]);

            return Professeur::create([
                ...array_diff_key($data, array_flip(['login_email'])),
                'statut' => 'actif',
                'user_id' => $user->id,
            ]);
        });

        if (! $envoyerInvitation) {
            return ['professeur' => $professeur->load('user'), 'mail_envoye' => false, 'lien' => null];
        }

        $envoi = $this->acces->envoyer($professeur->user, AccesCompteService::INVITATION, AccesCompteService::DUREE_ADMIN_MINUTES);

        return ['professeur' => $professeur->load('user'), 'mail_envoye' => $envoi['envoye'], 'lien' => $envoi['lien']];
    }

    /**
     * Séances à venir encore assignées, assignations actives et heures non finalisées.
     *
     * @return array{classes_actives: int, seances_a_venir: int, heures_en_attente: int, classes_sans_autre_professeur: list<string>}
     */
    public function impact(Professeur $professeur): array
    {
        $actives = $professeur->assignations()->actif()->with('classe.cours')->get();

        $seances = SessionProfesseur::query()
            ->where('professeur_id', $professeur->id)
            ->where('remplace', false)
            ->whereIn('course_session_id', CourseSession::query()->select('id')->where('date', '>=', $this->aujourdhui()))
            ->count();

        $seuls = $actives->filter(fn (ProfesseurClasse $a) => ! ProfesseurClasse::query()
            ->where('classe_id', $a->classe_id)->where('professeur_id', '!=', $professeur->id)->actif()->exists())
            ->map(fn (ProfesseurClasse $a) => ($a->classe->cours->titre ?? "Classe")." (#{$a->classe_id})")->values()->all();

        return [
            'classes_actives' => $actives->count(),
            'seances_a_venir' => $seances,
            'heures_en_attente' => $professeur->timesheets()
                ->whereIn('statut_validation', [Timesheet::STATUT_BROUILLON, Timesheet::STATUT_SOUMIS])->count(),
            'classes_sans_autre_professeur' => $seuls,
        ];
    }

    /** @return array{professeur: Professeur, assignations_terminees: int} */
    public function desactiver(Professeur $professeur, bool $terminerAssignations = true, ?string $dateSortie = null): array
    {
        if ($professeur->statut === 'inactif') {
            throw RegleMetierException::conflit('Ce professeur est déjà désactivé.');
        }

        return DB::transaction(function () use ($professeur, $terminerAssignations, $dateSortie) {
            $terminees = 0;
            if ($terminerAssignations) {
                foreach ($professeur->assignations()->actif()->get() as $assignation) {
                    $this->assignations->terminer($assignation);
                    $terminees++;
                }
            }

            $professeur->update(['statut' => 'inactif', 'date_sortie' => $dateSortie ?? $this->aujourdhui()]);
            $professeur->user->tokens()->delete();
            $this->acces->annulerLiens($professeur->user);

            return ['professeur' => $professeur->refresh(), 'assignations_terminees' => $terminees];
        });
    }

    /** @return array{professeur: Professeur, mail_envoye: bool, lien: ?string} */
    public function reactiver(Professeur $professeur, bool $envoyerInvitation = true): array
    {
        if ($professeur->statut === 'actif') {
            throw RegleMetierException::conflit('Ce professeur est déjà actif.');
        }

        DB::transaction(function () use ($professeur) {
            $professeur->update(['statut' => 'actif', 'date_sortie' => null]);
            // L'ancien mot de passe n'est plus de confiance : le compte repasse par une invitation.
            $professeur->user->forceFill([
                'password' => $this->acces->motDePasseInutilisable(),
                'must_change_password' => false,
                'mot_de_passe_defini_le' => null,
                'invitation_envoyee_le' => null,
                'invitation_echec_le' => null,
            ])->save();
            $professeur->user->tokens()->delete();
        });

        if (! $envoyerInvitation) {
            return ['professeur' => $professeur->refresh(), 'mail_envoye' => false, 'lien' => null];
        }

        $envoi = $this->acces->envoyer($professeur->user, AccesCompteService::INVITATION, AccesCompteService::DUREE_ADMIN_MINUTES);

        return ['professeur' => $professeur->refresh(), 'mail_envoye' => $envoi['envoye'], 'lien' => $envoi['lien']];
    }

    /**
     * Renvoie l'invitation (mot de passe jamais défini) ou envoie un lien de réinitialisation.
     *
     * @return array{mail_envoye: bool, lien: ?string}
     */
    public function envoyerLien(Professeur $professeur): array
    {
        $this->assertActif($professeur);

        $attente = $this->acces->attenteEnvoi($professeur->user);
        if ($attente > 0) {
            throw new RegleMetierException(
                'Un email vient d\'être envoyé. Réessayez dans '.$attente.' seconde'.($attente > 1 ? 's' : '').'.', 429);
        }

        $envoi = $this->acces->envoyer($professeur->user, $this->acces->typePour($professeur->user),
            AccesCompteService::DUREE_ADMIN_MINUTES, parDirection: true);

        return ['mail_envoye' => $envoi['envoye'], 'lien' => $envoi['lien']];
    }

    /** Lien à transmettre soi-même (affiché une fois) quand l'email n'arrive pas. */
    public function genererLien(Professeur $professeur, int $parUserId): string
    {
        $this->assertActif($professeur);

        return $this->acces->genererLienManuel($professeur->user, $parUserId);
    }

    public function changerEmailConnexion(Professeur $professeur, string $email): Professeur
    {
        $user = $professeur->user;
        DB::transaction(function () use ($user, $email) {
            $user->update(['email' => $email]);
            $user->tokens()->delete();
            $this->acces->annulerLiens($user);
        });

        return $professeur->refresh()->load('user');
    }

    /** RG : suppression réservée à un professeur créé par erreur, sans aucune donnée liée. */
    public function supprimer(Professeur $professeur): void
    {
        if ($professeur->timesheets()->exists() || $professeur->assignations()->exists()
            || SessionProfesseur::query()->where('professeur_id', $professeur->id)->exists()) {
            throw RegleMetierException::conflit('Ce professeur a des données liées (classes, séances ou heures) : désactivez-le plutôt que de le supprimer.');
        }

        $professeur->user->delete();
    }

    private function assertActif(Professeur $professeur): void
    {
        if ($professeur->statut !== 'actif') {
            throw RegleMetierException::conflit('Ce professeur est désactivé : réactivez-le d\'abord.');
        }
    }

    private function aujourdhui(): string
    {
        return now('Europe/Brussels')->toDateString();
    }
}
