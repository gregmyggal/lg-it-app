<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\SignatureSpecimen;
use App\Models\Timesheet;
use App\Models\TimesheetPdf;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PROF-01 : cycle de vie du professeur et de son compte (création, archivage, réactivation, envoi de lien,
 * email de connexion). PROF-02 : archivage et suppression (forçable) libèrent toutes ses séances à venir. ADMIN-03 : aucun mot de passe n'est généré ni communiqué ; l'accès passe par un lien à usage
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
     * PROF-02 : ce qu'un archivage (ou une suppression) changerait sur les classes et les séances à venir.
     * Les compteurs portent sur les séances non annulées ; à l'archivage, une séance à venir avec une heure
     * encodée par ce professeur lui reste assignée (RG-3).
     *
     * @return array<string, mixed>
     */
    public function impact(Professeur $professeur, bool $suppression = false): array
    {
        $actives = $professeur->assignations()->actif()->with('classe.periodes.cours')->get();

        $lignes = $this->lignesFutures($professeur, ! $suppression)
            ->with('session.classePeriode.cours')
            ->get()
            ->filter(fn (SessionProfesseur $l) => ! $l->session->isAnnulee());

        $conservees = $suppression ? 0 : $this->lignesFutures($professeur, false)
            ->whereHas('session', fn ($q) => $q->where('statut', '!=', CourseSession::STATUT_ANNULEE))
            ->count() - $lignes->count();

        // Séance sans professeur après nettoyage : plus aucune autre ligne non remplacée.
        $sansProfesseur = $lignes->filter(fn (SessionProfesseur $l) => ! SessionProfesseur::query()
            ->where('course_session_id', $l->course_session_id)
            ->where('professeur_id', '!=', $professeur->id)
            ->where('remplace', false)
            ->exists())
            ->sortBy(fn (SessionProfesseur $l) => $l->session->date->toDateString().$l->session->heure_debut)
            ->values();

        $seuls = $actives->filter(fn (ProfesseurClasse $a) => ! ProfesseurClasse::query()
            ->where('classe_id', $a->classe_id)->where('professeur_id', '!=', $professeur->id)->actif()->exists())
            ->map(fn (ProfesseurClasse $a) => $this->nomClasse($a->classe))->values()->all();

        return [
            'classes_actives' => $actives->count(),
            'classes' => $actives->map(fn (ProfesseurClasse $a) => ['id' => $a->classe_id, 'nom' => $this->nomClasse($a->classe)])->values()->all(),
            'seances_a_venir' => $lignes->count(),
            'detail_seances' => [
                'classe' => $lignes->filter(fn ($l) => ! $l->remplace && $l->origine === SessionProfesseur::ORIGINE_CLASSE)->count(),
                'ajout' => $lignes->filter(fn ($l) => ! $l->remplace && $l->origine === SessionProfesseur::ORIGINE_AJOUT)->count(),
                'remplacement' => $lignes->filter(fn ($l) => ! $l->remplace && $l->origine === SessionProfesseur::ORIGINE_REMPLACEMENT)->count(),
                'deja_remplace' => $lignes->where('remplace', true)->count(),
            ],
            'seances_conservees_avec_heures' => $conservees,
            'seances_sans_professeur' => $sansProfesseur->count(),
            'seances_sans_professeur_liste' => $sansProfesseur->take(20)->map(fn (SessionProfesseur $l) => [
                'session_id' => $l->course_session_id,
                'classe_id' => $l->session->classe_id,
                'date' => $l->session->date->toDateString(),
                'heure_debut' => substr($l->session->heure_debut, 0, 5),
                'classe' => $l->session->classePeriode?->cours?->titre,
            ])->all(),
            'heures_en_attente' => $professeur->timesheets()
                ->whereIn('statut_validation', [Timesheet::STATUT_BROUILLON, Timesheet::STATUT_SOUMIS])->count(),
            'classes_sans_autre_professeur' => $seuls,
        ];
    }

    /**
     * PROF-02 : archivage (statut technique `inactif`). Les assignations actives sont terminées et le professeur est
     * retiré de toutes les séances à venir (obligatoire, RG-4), sauf celles où il a déjà encodé une heure.
     *
     * @return array{professeur: Professeur, assignations_terminees: int, seances_liberees: int}
     */
    public function desactiver(Professeur $professeur, ?string $dateSortie = null): array
    {
        if ($professeur->statut === 'inactif') {
            throw RegleMetierException::conflit('Ce professeur est déjà archivé.');
        }

        return DB::transaction(function () use ($professeur, $dateSortie) {
            // Compté avant : terminer() retire déjà une partie des lignes de classe.
            $liberees = $this->lignesFutures($professeur, true)
                ->whereHas('session', fn ($q) => $q->where('statut', '!=', CourseSession::STATUT_ANNULEE))->count();
            $terminees = 0;
            foreach ($professeur->assignations()->actif()->get() as $assignation) {
                $this->assignations->terminer($assignation);
                $terminees++;
            }
            $this->libererSeancesFutures($professeur, garderAvecHeures: true);

            $professeur->update(['statut' => 'inactif', 'date_sortie' => $dateSortie ?? $this->aujourdhui()]);
            $professeur->user->tokens()->delete();
            $this->acces->annulerLiens($professeur->user);

            return ['professeur' => $professeur->refresh(), 'assignations_terminees' => $terminees, 'seances_liberees' => $liberees];
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

    /**
     * PROF-02 : ce qu'une suppression ferait perdre (heures par statut, fiches générées, tarifs, séances passées)
     * et si le forçage est permis à cet utilisateur (heures sur une fiche générée : admin seulement).
     *
     * @return array<string, mixed>
     */
    public function resumeSuppression(Professeur $professeur, User $auteur): array
    {
        $heures = $professeur->timesheets()->get(['id', 'nombre_heures', 'statut_validation']);
        $generees = $heures->where('statut_validation', Timesheet::STATUT_GENERE)->count();
        $fiches = TimesheetPdf::where('professeur_id', $professeur->id)
            ->select('annee', 'mois')->distinct()->orderByDesc('annee')->orderByDesc('mois')->get();

        return [
            'heures' => [
                'nb' => $heures->count(),
                'total' => (float) $heures->sum('nombre_heures'),
                'par_statut' => $heures->groupBy('statut_validation')
                    ->map(fn ($groupe, $statut) => ['statut' => $statut, 'nb' => $groupe->count(), 'total' => (float) $groupe->sum('nombre_heures')])
                    ->values(),
            ],
            'heures_generees' => $generees,
            'fiches' => $fiches->map(fn ($f) => ['annee' => (int) $f->annee, 'mois' => (int) $f->mois])->values(),
            'tarifs' => $professeur->tarifs()->count(),
            'seances_passees' => SessionProfesseur::query()
                ->where('professeur_id', $professeur->id)
                ->whereIn('course_session_id', CourseSession::query()->select('id')->where(fn ($q) => $q
                    ->where('date', '<', $this->aujourdhui())
                    ->orWhereIn('statut', [CourseSession::STATUT_EN_COURS, CourseSession::STATUT_TERMINEE])))
                ->count(),
            'classes' => $professeur->assignations()->count(),
            'forcage_requis' => $heures->isNotEmpty(),
            'forcable' => $generees === 0 || $auteur->isAdmin(),
            'impact' => $this->impact($professeur, suppression: true),
        ];
    }

    /**
     * PROF-02 : suppression physique du professeur et de son compte. Sans heure encodée : directe. Avec heures :
     * forçage (motif) obligatoire ; heures sur une fiche générée : admin seulement. Ses séances à venir sont
     * libérées comme à l'archivage, puis la cascade des FK emporte heures, tarifs, fiches, signatures et audits.
     */
    public function supprimer(Professeur $professeur, User $auteur, ?string $motifForcage = null): void
    {
        if ($professeur->user->role !== 'professeur') {
            throw RegleMetierException::invalide('Ce compte n\'est pas un compte professeur : il ne peut pas être supprimé d\'ici.');
        }

        $resume = $this->resumeSuppression($professeur, $auteur);
        if ($resume['forcage_requis'] && ! $resume['forcable']) {
            throw RegleMetierException::conflit(
                'Des fiches de défraiement ont déjà été générées pour ce professeur : seul un administrateur peut forcer sa suppression. Archivez-le.',
                ['resume' => $resume, 'forcable' => false]
            );
        }
        if ($resume['forcage_requis'] && $motifForcage === null) {
            throw RegleMetierException::conflit(
                'Ce professeur a des heures encodées : archivez-le, ou forcez la suppression.',
                ['resume' => $resume, 'forcable' => true]
            );
        }

        $fichiers = array_merge(
            TimesheetPdf::where('professeur_id', $professeur->id)->pluck('chemin')->all(),
            TimesheetSignature::where('professeur_id', $professeur->id)->pluck('specimen_chemin')->all(),
            SignatureSpecimen::where('user_id', $professeur->user_id)->pluck('chemin')->all(),
        );

        DB::transaction(function () use ($professeur) {
            $this->libererSeancesFutures($professeur, garderAvecHeures: false);
            $professeur->user->delete();
        });

        Storage::disk('local')->delete(array_values(array_filter($fichiers)));

        Log::info('Professeur supprimé (PROF-02)', [
            'professeur_id' => $professeur->id,
            'nom' => trim("{$professeur->prenom} {$professeur->nom}"),
            'user_id' => $auteur->id,
            'force' => $motifForcage !== null,
            'motif' => $motifForcage,
            'heures' => $resume['heures'],
            'heures_generees' => $resume['heures_generees'],
            'seances_liberees' => $resume['impact']['seances_a_venir'],
        ]);
    }

    /**
     * PROF-02 RG-1 : retire le professeur de toutes les séances à venir (date ≥ aujourd'hui, planifiées ou annulées),
     * quelle que soit l'origine de la ligne. La séance reste valable :
     * - il remplaçait A → A reste remplacé, sans remplaçant (« remplaçant à trouver »), ou par son propre remplaçant ;
     * - il était remplacé par C → la ligne de C devient un ajout ponctuel.
     *
     * @return int séances libérées
     */
    public function libererSeancesFutures(Professeur $professeur, bool $garderAvecHeures): int
    {
        $lignes = $this->lignesFutures($professeur, $garderAvecHeures)->get();

        foreach ($lignes as $ligne) {
            SessionProfesseur::query()
                ->where('course_session_id', $ligne->course_session_id)
                ->where('remplace_par_professeur_id', $professeur->id)
                ->update(['remplace_par_professeur_id' => $ligne->remplace ? $ligne->remplace_par_professeur_id : null]);

            if ($ligne->remplace && $ligne->remplace_par_professeur_id !== null && $ligne->origine !== SessionProfesseur::ORIGINE_REMPLACEMENT) {
                SessionProfesseur::query()
                    ->where('course_session_id', $ligne->course_session_id)
                    ->where('professeur_id', $ligne->remplace_par_professeur_id)
                    ->where('origine', SessionProfesseur::ORIGINE_REMPLACEMENT)
                    ->update(['origine' => SessionProfesseur::ORIGINE_AJOUT]);
            }

            $ligne->delete();
        }

        return $lignes->count();
    }

    /** Lignes du professeur sur les séances à venir (hors commencées / terminées), éventuellement sans celles avec heure encodée. */
    private function lignesFutures(Professeur $professeur, bool $sansHeures)
    {
        return SessionProfesseur::query()
            ->where('professeur_id', $professeur->id)
            ->whereIn('course_session_id', CourseSession::query()->select('id')
                ->where('date', '>=', $this->aujourdhui())
                ->whereIn('statut', [CourseSession::STATUT_PLANIFIEE, CourseSession::STATUT_ANNULEE]))
            ->when($sansHeures, fn ($q) => $q->whereNotExists(fn ($t) => $t->selectRaw('1')->from('timesheets')
                ->whereColumn('timesheets.course_session_id', 'session_professors.course_session_id')
                ->whereColumn('timesheets.professeur_id', 'session_professors.professeur_id')));
    }

    private function nomClasse(?Classe $classe): string
    {
        return ($classe ? ClasseResource::titreDe($classe) : '') ?: 'Classe'.($classe ? " (#{$classe->id})" : '');
    }

    private function assertActif(Professeur $professeur): void
    {
        if ($professeur->statut !== 'actif') {
            throw RegleMetierException::conflit('Ce professeur est archivé : réactivez-le d\'abord.');
        }
    }

    private function aujourdhui(): string
    {
        return now('Europe/Brussels')->toDateString();
    }
}
