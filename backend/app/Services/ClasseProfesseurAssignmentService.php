<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service unique d'assignation professeur ⇄ classe (RG-4, RG-8) : même logique depuis la classe ou
 * depuis le professeur. Transactionnel, idempotent. L'assignation est propagée aux sessions À VENIR
 * (date ≥ aujourd'hui en Europe/Brussels, statut planifiee/en_cours, annulées exclues) ; les sessions
 * passées ne sont jamais modifiées. Le rôle est indicatif (aucun effet sur rémunération/timesheets).
 */
class ClasseProfesseurAssignmentService
{
    /**
     * Aperçu sans écriture : comptage de la propagation et conflits d'horaire.
     *
     * @param  array{date_debut?: ?string, date_fin?: ?string}  $data
     * @return array{sessions_assignees: int, sessions_passees_ignorees: int, sessions_deja_assignees: int, conflits: list<array<string, mixed>>}
     */
    public function apercu(Classe $classe, Professeur $professeur, array $data = []): array
    {
        $existante = $this->assignation($classe, $professeur);
        [$debut, $fin] = $this->fenetre($data, $existante, true);

        $plan = $this->plan($classe, $professeur, $debut, $fin);

        return $this->recapitulatif($plan) + ['conflits' => $plan['conflits']];
    }

    /**
     * Crée (ou réactive) l'assignation puis propage aux sessions à venir.
     *
     * @param  array{role?: ?string, date_debut?: ?string, date_fin?: ?string}  $data
     * @return array{assignation: ProfesseurClasse, recapitulatif: array<string, int>, cree: bool}
     *
     * @throws RegleMetierException 422 si un conflit d'horaire bloque l'assignation
     */
    public function assigner(Classe $classe, Professeur $professeur, array $data = []): array
    {
        if ($professeur->statut === 'inactif') {
            throw RegleMetierException::conflit('Ce professeur est désactivé : il ne peut plus être assigné à une classe.');
        }

        return DB::transaction(function () use ($classe, $professeur, $data) {
            $existante = $this->assignation($classe, $professeur);
            [$debut, $fin] = $this->fenetre($data, $existante, true);

            $plan = $this->plan($classe, $professeur, $debut, $fin);
            $this->refuserSiConflits($plan['conflits']);

            $cree = $existante === null || ! $existante->isActif();
            $role = $data['role'] ?? $existante?->role ?? ProfesseurClasse::ROLE_CO_ENSEIGNANT;

            $assignation = $existante ?? new ProfesseurClasse(['professeur_id' => $professeur->id, 'classe_id' => $classe->id]);
            $assignation->fill(['role' => $role, 'date_debut' => $debut, 'date_fin' => $fin])->save();

            $this->creerLignes($plan['a_assigner'], $professeur->id, $role);

            return [
                'assignation' => $assignation->refresh(),
                'recapitulatif' => $this->recapitulatif($plan),
                'cree' => $cree,
            ];
        });
    }

    /**
     * Modifie rôle / dates. Une fin de validité retire le professeur des sessions futures sans timesheet ;
     * une assignation (ré)ouverte est re-propagée (conflits bloquants comme à l'assignation).
     *
     * @param  array{role?: string, date_debut?: string, date_fin?: ?string}  $data
     * @return array{assignation: ProfesseurClasse, recapitulatif: array<string, int>}
     */
    public function modifier(ProfesseurClasse $assignation, array $data): array
    {
        return DB::transaction(function () use ($assignation, $data) {
            $classe = $assignation->classe;
            $professeur = $assignation->professeur;
            [$debut, $fin] = $this->fenetre($data, $assignation, false);

            $plan = $this->plan($classe, $professeur, $debut, $fin);
            $this->refuserSiConflits($plan['conflits']);

            $role = $data['role'] ?? $assignation->role;
            $assignation->update(['role' => $role, 'date_debut' => $debut, 'date_fin' => $fin]);

            if ($assignation->wasChanged('role')) {
                $this->sessionsAVenir($classe)->pluck('id')->chunk(500)->each(
                    fn ($ids) => SessionProfesseur::whereIn('course_session_id', $ids)
                        ->where('professeur_id', $professeur->id)
                        ->where('origine', SessionProfesseur::ORIGINE_CLASSE)
                        ->where('remplace', false)
                        ->update(['role' => $role])
                );
            }

            $this->creerLignes($plan['a_assigner'], $professeur->id, $role);
            $retrait = $fin !== null ? $this->retirer($assignation, $fin) : ['sessions_retirees' => 0, 'sessions_conservees' => 0];

            return [
                'assignation' => $assignation->refresh(),
                'recapitulatif' => $this->recapitulatif($plan) + $retrait,
            ];
        });
    }

    /**
     * Termine l'assignation (date_fin, défaut aujourd'hui) : retire le professeur des sessions futures
     * sans timesheet ; la ligne professeur_classe est conservée (historique, réactivation possible).
     *
     * @return array{assignation: ProfesseurClasse, recapitulatif: array<string, int>}
     *
     * @throws RegleMetierException 409 si déjà terminée ; 422 si la fin précède le début
     */
    public function terminer(ProfesseurClasse $assignation, ?string $dateFin = null): array
    {
        if (! $assignation->isActif()) {
            throw RegleMetierException::conflit('Cette assignation est déjà terminée.');
        }

        $fin = $dateFin ?? max($this->aujourdhui(), $assignation->date_debut->toDateString());
        $this->assertFinValide($assignation->date_debut->toDateString(), $fin);

        return DB::transaction(function () use ($assignation, $fin) {
            $assignation->update(['date_fin' => $fin]);

            return ['assignation' => $assignation->refresh(), 'recapitulatif' => $this->retirer($assignation, $fin)];
        });
    }

    /**
     * Propage les professeurs ACTIFS de la classe (à la date de chaque session) aux sessions données
     * (génération, bis). Idempotent : une ligne existante, y compris « remplacé », n'est jamais écrasée.
     *
     * @param  iterable<CourseSession>  $sessions
     * @return int nombre de lignes créées
     */
    public function propagerAuxSessions(Classe $classe, iterable $sessions): int
    {
        $assignations = ProfesseurClasse::where('classe_id', $classe->id)->get();
        if ($assignations->isEmpty()) {
            return 0;
        }

        $lignes = [];
        foreach ($sessions as $session) {
            $date = $session->date->toDateString();
            foreach ($assignations as $a) {
                if ($a->date_debut->toDateString() <= $date && ($a->date_fin === null || $a->date_fin->toDateString() >= $date)) {
                    $lignes[] = $this->ligne($session->id, $a->professeur_id, $a->role);
                }
            }
        }

        return $lignes ? SessionProfesseur::insertOrIgnore($lignes) : 0;
    }

    /**
     * Conflits d'horaire d'un professeur sur les sessions données : autres sessions (non annulées) où il
     * est assigné non remplacé, le même jour, sur un horaire qui se chevauche.
     *
     * @param  iterable<CourseSession>  $sessions
     * @return list<array<string, mixed>>
     */
    public function conflits(iterable $sessions, int $professeurId): array
    {
        $sessions = collect($sessions);
        if ($sessions->isEmpty()) {
            return [];
        }

        $autres = CourseSession::query()
            ->select('course_sessions.*')
            ->join('session_professors', 'session_professors.course_session_id', '=', 'course_sessions.id')
            ->where('session_professors.professeur_id', $professeurId)
            ->where('session_professors.remplace', false)
            ->where('course_sessions.statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereIn('course_sessions.date', $sessions->map(fn ($s) => $s->date->toDateString())->unique()->values())
            ->with('classe.cours')
            ->get()
            ->groupBy(fn (CourseSession $s) => $s->date->toDateString());

        $conflits = [];
        foreach ($sessions as $session) {
            foreach ($autres->get($session->date->toDateString(), []) as $autre) {
                if ($autre->id !== $session->id && $autre->heure_debut < $session->heure_fin && $autre->heure_fin > $session->heure_debut) {
                    $conflits[] = [
                        'date' => $session->date->toDateString(),
                        'session_id' => $session->id,
                        'session_en_conflit_id' => $autre->id,
                        'classe_id' => $autre->classe_id,
                        'classe' => $autre->classe->cours->titre ?? null,
                        'heure_debut' => substr($autre->heure_debut, 0, 5),
                        'heure_fin' => substr($autre->heure_fin, 0, 5),
                    ];
                }
            }
        }

        return $conflits;
    }

    public function assignation(Classe $classe, Professeur $professeur): ?ProfesseurClasse
    {
        return ProfesseurClasse::where('classe_id', $classe->id)->where('professeur_id', $professeur->id)->first();
    }

    // ---------------------------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: ?string} [date_debut, date_fin] retenues
     */
    private function fenetre(array $data, ?ProfesseurClasse $existante, bool $reouverture): array
    {
        $debut = $data['date_debut'] ?? $existante?->date_debut?->toDateString() ?? $this->aujourdhui();
        // POST : date_fin absente = assignation ouverte (réactivation) ; PUT : absente = inchangée.
        $fin = array_key_exists('date_fin', $data)
            ? $data['date_fin']
            : ($reouverture ? null : $existante?->date_fin?->toDateString());

        if ($fin !== null) {
            $this->assertFinValide($debut, $fin);
        }

        return [$debut, $fin];
    }

    private function assertFinValide(string $debut, string $fin): void
    {
        if ($fin < $debut) {
            $message = 'La date de fin doit être postérieure ou égale à la date de début.';
            throw RegleMetierException::invalide($message, ['date_fin' => [$message]]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $conflits
     */
    private function refuserSiConflits(array $conflits): void
    {
        if ($conflits === []) {
            return;
        }

        $message = 'Conflit d\'horaire : ce professeur est déjà assigné à une autre session au même moment ('.count($conflits).').';
        throw RegleMetierException::invalide($message, ['professeur_id' => [$message]], ['conflits' => $conflits]);
    }

    /**
     * @return array{a_assigner: Collection<int, CourseSession>, deja: int, passees: int, conflits: list<array<string, mixed>>}
     */
    private function plan(Classe $classe, Professeur $professeur, string $debut, ?string $fin): array
    {
        $aujourdhui = $this->aujourdhui();

        $fenetre = $this->sessionsAVenir($classe)
            ->where('date', '>=', max($debut, $aujourdhui))
            ->when($fin !== null, fn ($q) => $q->where('date', '<=', $fin))
            ->get();

        $deja = SessionProfesseur::where('professeur_id', $professeur->id)
            ->whereIn('course_session_id', $fenetre->pluck('id'))
            ->pluck('course_session_id');

        $aAssigner = $fenetre->reject(fn (CourseSession $s) => $deja->contains($s->id))->values();

        $passees = CourseSession::where('classe_id', $classe->id)
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->where(fn ($q) => $q->where('date', '<', $aujourdhui)->orWhere('statut', CourseSession::STATUT_TERMINEE))
            ->count();

        return [
            'a_assigner' => $aAssigner,
            'deja' => $deja->count(),
            'passees' => $passees,
            'conflits' => $this->conflits($aAssigner, $professeur->id),
        ];
    }

    /** Sessions à venir d'une classe : date ≥ aujourd'hui, planifiee/en_cours (annulées et terminées exclues). */
    private function sessionsAVenir(Classe $classe)
    {
        return CourseSession::query()
            ->where('classe_id', $classe->id)
            ->where('date', '>=', $this->aujourdhui())
            ->whereIn('statut', [CourseSession::STATUT_PLANIFIEE, CourseSession::STATUT_EN_COURS]);
    }

    /**
     * @param  array{a_assigner: Collection<int, CourseSession>, deja: int, passees: int}  $plan
     * @return array{sessions_assignees: int, sessions_passees_ignorees: int, sessions_deja_assignees: int}
     */
    private function recapitulatif(array $plan): array
    {
        return [
            'sessions_assignees' => $plan['a_assigner']->count(),
            'sessions_passees_ignorees' => $plan['passees'],
            'sessions_deja_assignees' => $plan['deja'],
        ];
    }

    /** @param  Collection<int, CourseSession>  $sessions */
    private function creerLignes(Collection $sessions, int $professeurId, string $role): void
    {
        $lignes = $sessions->map(fn (CourseSession $s) => $this->ligne($s->id, $professeurId, $role))->all();

        if ($lignes) {
            SessionProfesseur::insertOrIgnore($lignes);
        }
    }

    /** @return array<string, mixed> */
    private function ligne(int $sessionId, int $professeurId, string $role): array
    {
        return [
            'course_session_id' => $sessionId,
            'professeur_id' => $professeurId,
            'role' => $role,
            'origine' => SessionProfesseur::ORIGINE_CLASSE,
            'remplace' => false,
            'remplace_par_professeur_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Retire le professeur des sessions futures (date ≥ aujourd'hui) postérieures à la fin de validité, sauf
     * celles avec timesheet. Seules les lignes issues de la classe et non « remplacées » sont retirées.
     *
     * @return array{sessions_retirees: int, sessions_conservees: int}
     */
    private function retirer(ProfesseurClasse $assignation, string $fin): array
    {
        $lignes = SessionProfesseur::query()
            ->where('professeur_id', $assignation->professeur_id)
            ->where('origine', SessionProfesseur::ORIGINE_CLASSE)
            ->where('remplace', false)
            ->whereIn('course_session_id', CourseSession::query()
                ->select('id')
                ->where('classe_id', $assignation->classe_id)
                ->where('date', '>=', $this->aujourdhui())
                ->where('date', '>', $fin));

        $avecTimesheet = (clone $lignes)->whereExists(fn ($q) => $q->selectRaw('1')->from('timesheets')
            ->whereColumn('timesheets.course_session_id', 'session_professors.course_session_id')
            ->whereColumn('timesheets.professeur_id', 'session_professors.professeur_id'))->count();

        $retirees = $lignes->whereNotExists(fn ($q) => $q->selectRaw('1')->from('timesheets')
            ->whereColumn('timesheets.course_session_id', 'session_professors.course_session_id')
            ->whereColumn('timesheets.professeur_id', 'session_professors.professeur_id'))->delete();

        return ['sessions_retirees' => $retirees, 'sessions_conservees' => $avecTimesheet];
    }

    private function aujourdhui(): string
    {
        return now('Europe/Brussels')->toDateString();
    }
}
