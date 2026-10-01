<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Mail\InvitationProfesseurMail;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * PROF-01 : cycle de vie du professeur et de son compte (création, désactivation, réactivation, reset,
 * email de connexion). Le mot de passe provisoire est généré ici, envoyé par mail et renvoyé une seule fois.
 */
class ProfesseurCompteService
{
    public function __construct(private readonly ClasseProfesseurAssignmentService $assignations) {}

    /** @return array{professeur: Professeur, mot_de_passe: string, mail_envoye: bool} */
    public function creer(array $data): array
    {
        $motDePasse = $this->genererMotDePasse();

        $professeur = DB::transaction(function () use ($data, $motDePasse) {
            $user = User::create([
                'name' => $data['prenom'].' '.$data['nom'],
                'email' => $data['login_email'],
                'password' => $motDePasse,
                'role' => 'professeur',
            ]);
            $user->forceFill(['must_change_password' => true])->save();

            return Professeur::create([
                ...array_diff_key($data, array_flip(['login_email'])),
                'statut' => 'actif',
                'user_id' => $user->id,
            ]);
        });

        return [
            'professeur' => $professeur->load('user'),
            'mot_de_passe' => $motDePasse,
            'mail_envoye' => $this->inviter($professeur, $motDePasse, 'creation'),
        ];
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

            return ['professeur' => $professeur->refresh(), 'assignations_terminees' => $terminees];
        });
    }

    /** @return array{professeur: Professeur, mot_de_passe: string, mail_envoye: bool} */
    public function reactiver(Professeur $professeur): array
    {
        if ($professeur->statut === 'actif') {
            throw RegleMetierException::conflit('Ce professeur est déjà actif.');
        }

        $motDePasse = $this->genererMotDePasse();
        DB::transaction(function () use ($professeur, $motDePasse) {
            $professeur->update(['statut' => 'actif', 'date_sortie' => null]);
            $this->definirMotDePasse($professeur->user, $motDePasse);
        });

        return ['professeur' => $professeur->refresh(), 'mot_de_passe' => $motDePasse,
            'mail_envoye' => $this->inviter($professeur, $motDePasse, 'reactivation')];
    }

    /** @return array{mot_de_passe: string, mail_envoye: bool} */
    public function reinitialiserMotDePasse(Professeur $professeur): array
    {
        $this->assertActif($professeur);
        $motDePasse = $this->genererMotDePasse();
        $this->definirMotDePasse($professeur->user, $motDePasse);

        return ['mot_de_passe' => $motDePasse, 'mail_envoye' => $this->inviter($professeur, $motDePasse, 'reinitialisation')];
    }

    public function changerEmailConnexion(Professeur $professeur, string $email): Professeur
    {
        $user = $professeur->user;
        DB::transaction(function () use ($user, $email) {
            $user->update(['email' => $email]);
            $user->tokens()->delete();
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

    private function definirMotDePasse(User $user, string $motDePasse): void
    {
        $user->forceFill(['password' => $motDePasse, 'must_change_password' => true])->save();
        $user->tokens()->delete();
    }

    private function genererMotDePasse(): string
    {
        return Str::password(12, symbols: false);
    }

    /** L'échec d'envoi ne bloque pas l'action : le mot de passe est de toute façon affiché une fois à l'écran. */
    private function inviter(Professeur $professeur, string $motDePasse, string $motif): bool
    {
        try {
            Mail::to($professeur->email)->send(new InvitationProfesseurMail(
                $professeur->prenom, $professeur->user->email, $motDePasse, $motif));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function aujourdhui(): string
    {
        return now('Europe/Brussels')->toDateString();
    }
}
