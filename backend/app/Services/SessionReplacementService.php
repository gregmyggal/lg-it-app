<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

/**
 * Remplacement (RG-9, AC-14, AC-25) et ajout ponctuel (CLS-04) d'un professeur sur UNE session. Possible sur une session
 * passée ou à venir (pas annulée), sans aucune contrainte liée aux timesheets : aucune timesheet n'est
 * modifiée, bloquée ni transférée.
 */
class SessionReplacementService
{
    public function __construct(private readonly ClasseProfesseurAssignmentService $assignations) {}

    /**
     * @return array{session: CourseSession, avertissements: list<array<string, mixed>>}
     *
     * @throws RegleMetierException 409 session annulée ; 422 professeurs invalides
     */
    public function remplacer(CourseSession $session, int $professeurRemplaceId, int $professeurRemplacantId): array
    {
        return DB::transaction(function () use ($session, $professeurRemplaceId, $professeurRemplacantId) {
            $session = CourseSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->isAnnulee()) {
                throw RegleMetierException::conflit('Une session annulée ne peut pas faire l\'objet d\'un remplacement.');
            }
            if ($professeurRemplaceId === $professeurRemplacantId) {
                $this->invalide('professeur_remplacant_id', 'Le remplaçant doit être différent du professeur remplacé.');
            }

            $ligneA = SessionProfesseur::where('course_session_id', $session->id)->where('professeur_id', $professeurRemplaceId)->lockForUpdate()->first();
            if (! $ligneA || $ligneA->remplace) {
                $this->invalide('professeur_remplace_id', 'Le professeur remplacé n\'est pas assigné à cette session (ou est déjà remplacé).');
            }

            $ligneB = SessionProfesseur::where('course_session_id', $session->id)->where('professeur_id', $professeurRemplacantId)->first();
            if ($ligneB) {
                $this->invalide('professeur_remplacant_id', $ligneB->remplace
                    ? 'Ce professeur a été remplacé sur cette session : annulez d\'abord ce remplacement.'
                    : 'Le remplaçant est déjà assigné à cette session.');
            }

            $ligneA->update(['remplace' => true, 'remplace_par_professeur_id' => $professeurRemplacantId]);
            SessionProfesseur::create([
                'course_session_id' => $session->id,
                'professeur_id' => $professeurRemplacantId,
                'role' => ProfesseurClasse::ROLE_REMPLACANT,
                'origine' => SessionProfesseur::ORIGINE_REMPLACEMENT,
                'remplace' => false,
            ]);

            return [
                'session' => $session,
                'avertissements' => array_map(
                    fn (array $c) => $c + ['message' => "Le remplaçant est déjà assigné à une autre session ce jour-là ({$c['classe']}, {$c['heure_debut']}–{$c['heure_fin']})."],
                    $this->assignations->conflits([$session], $professeurRemplacantId)
                ),
            ];
        });
    }

    /**
     * Ajoute un professeur à UNE session (passée ou à venir, pas annulée), sans remplacer personne.
     * Aucune timesheet n'est lue ni modifiée. Un conflit d'horaire est un avertissement non bloquant.
     *
     * @return array{session: CourseSession, avertissements: list<array<string, mixed>>}
     *
     * @throws RegleMetierException 409 session annulée ; 422 professeur invalide ou déjà présent (même remplacé)
     */
    public function ajouter(CourseSession $session, int $professeurId, string $role = ProfesseurClasse::ROLE_PRINCIPAL): array
    {
        return DB::transaction(function () use ($session, $professeurId, $role) {
            $session = CourseSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($session->isAnnulee()) {
                throw RegleMetierException::conflit('Une session annulée ne peut pas recevoir de professeur.');
            }
            if (! Professeur::whereKey($professeurId)->where('statut', '!=', 'inactif')->exists()) {
                $this->invalide('professeur_id', 'Ce professeur est introuvable ou inactif.');
            }

            $ligne = SessionProfesseur::where('course_session_id', $session->id)->where('professeur_id', $professeurId)->first();
            if ($ligne) {
                $this->invalide('professeur_id', $ligne->remplace
                    ? 'Ce professeur a été remplacé sur cette session : annulez d\'abord ce remplacement.'
                    : 'Ce professeur est déjà assigné à cette session.');
            }

            SessionProfesseur::create([
                'course_session_id' => $session->id,
                'professeur_id' => $professeurId,
                'role' => $role,
                'origine' => SessionProfesseur::ORIGINE_AJOUT,
                'remplace' => false,
            ]);

            return [
                'session' => $session,
                'avertissements' => array_map(
                    fn (array $c) => $c + ['message' => "Ce professeur est déjà assigné à une autre session ce jour-là ({$c['classe']}, {$c['heure_debut']}–{$c['heure_fin']})."],
                    $this->assignations->conflits([$session], $professeurId)
                ),
            ];
        });
    }

    /**
     * Retire un professeur ajouté ponctuellement. Refusé s'il a déjà une timesheet sur cette session,
     * si la ligne n'est pas un ajout, ou si elle est impliquée dans un remplacement en cours.
     *
     * @throws RegleMetierException 409
     */
    public function retirerAjout(CourseSession $session, int $professeurId): CourseSession
    {
        return DB::transaction(function () use ($session, $professeurId) {
            $ligne = SessionProfesseur::where('course_session_id', $session->id)->where('professeur_id', $professeurId)->lockForUpdate()->first();
            if (! $ligne || $ligne->origine !== SessionProfesseur::ORIGINE_AJOUT) {
                throw RegleMetierException::conflit('Ce professeur n\'a pas été ajouté ponctuellement à cette session : seul un ajout peut être retiré ici.');
            }
            if ($ligne->remplace || SessionProfesseur::where('course_session_id', $session->id)->where('remplace_par_professeur_id', $professeurId)->exists()) {
                throw RegleMetierException::conflit('Ce professeur est impliqué dans un remplacement : annulez d\'abord ce remplacement.');
            }
            if (Timesheet::where('course_session_id', $session->id)->where('professeur_id', $professeurId)->exists()) {
                throw RegleMetierException::conflit('Ce professeur a déjà encodé ses heures pour cette session : il ne peut plus être retiré.');
            }

            $ligne->delete();

            return $session;
        });
    }

    /**
     * Annule le remplacement du professeur A : A est restauré ; la ligne du remplaçant est supprimée si elle
     * n'a été créée que par ce remplacement (sinon, s'il est assigné à la classe ce jour-là, elle devient une ligne de classe).
     *
     * @throws RegleMetierException 409 si aucun remplacement à annuler ou remplaçant lui-même remplacé
     */
    public function annuler(CourseSession $session, int $professeurRemplaceId): CourseSession
    {
        return DB::transaction(function () use ($session, $professeurRemplaceId) {
            $ligneA = SessionProfesseur::where('course_session_id', $session->id)->where('professeur_id', $professeurRemplaceId)->lockForUpdate()->first();
            if (! $ligneA || ! $ligneA->remplace) {
                throw RegleMetierException::conflit('Aucun remplacement à annuler pour ce professeur sur cette session.');
            }

            $ligneB = SessionProfesseur::where('course_session_id', $session->id)
                ->where('professeur_id', $ligneA->remplace_par_professeur_id)->lockForUpdate()->first();

            if ($ligneB?->remplace) {
                throw RegleMetierException::conflit('Le remplaçant a lui-même été remplacé : annulez d\'abord ce remplacement.');
            }

            $ligneA->update(['remplace' => false, 'remplace_par_professeur_id' => null]);

            if ($ligneB && $ligneB->origine === SessionProfesseur::ORIGINE_REMPLACEMENT) {
                $assignationB = ProfesseurClasse::where('classe_id', $session->classe_id)
                    ->where('professeur_id', $ligneB->professeur_id)
                    ->actifA($session->date->toDateString())
                    ->first();

                $assignationB
                    ? $ligneB->update(['origine' => SessionProfesseur::ORIGINE_CLASSE, 'role' => $assignationB->role])
                    : $ligneB->delete();
            }

            return $session;
        });
    }

    private function invalide(string $champ, string $message): never
    {
        throw RegleMetierException::invalide($message, [$champ => [$message]]);
    }
}
