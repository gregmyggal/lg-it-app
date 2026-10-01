<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\Periode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service unique de génération des 14 séances hebdomadaires d'une classe (RG-2).
 * - saute les dates couvertes par une entrée non masquée du calendrier scolaire ;
 * - recale la première date sur le jour de la semaine de la classe ;
 * - refuse (422) si la 14e séance tombe après la fin de la période ;
 * - transactionnel et idempotent (generateSessions ne crée que les séances manquantes).
 */
class ClasseSessionGenerator
{
    public const NB_SEANCES = 14;

    /** Garde-fou contre une boucle infinie (calendrier anormalement rempli). */
    private const MAX_SEMAINES = 200;

    public function __construct(
        private readonly CalendrierScolaireService $calendrier,
        private readonly ClasseProfesseurAssignmentService $assignations,
    ) {}

    /**
     * Calcul pur (sans écriture) du plan de génération.
     *
     * @return array{
     *     date_premiere_session: string,
     *     recale: bool,
     *     seances: list<array{seance_numero: int, date: string}>,
     *     dates_sautees: list<array{date: string, libelle: string, type: string}>,
     *     blocage: ?array{message: string, periode_numero: int}
     * }
     */
    public function plan(int $anneeScolaireId, Periode $periode, int $jourSemaine, string $datePremiere): array
    {
        $demandee = Carbon::parse($datePremiere)->startOfDay();
        $courante = $demandee->copy();
        if ($courante->dayOfWeekIso !== $jourSemaine) {
            $courante->addDays(($jourSemaine - $courante->dayOfWeekIso + 7) % 7);
        }
        $premiere = $courante->copy();

        $entrees = $this->calendrier->entreesActives($anneeScolaireId);
        $seances = [];
        $sautees = [];

        for ($i = 0; $i < self::MAX_SEMAINES && count($seances) < self::NB_SEANCES; $i++) {
            $date = $courante->toDateString();
            $entree = $this->calendrier->entreeCouvrant($entrees, $date);

            if ($entree) {
                $sautees[] = ['date' => $date, 'libelle' => $entree->libelle, 'type' => $entree->type];
            } else {
                $seances[] = ['seance_numero' => count($seances) + 1, 'date' => $date];
            }

            $courante->addWeek();
        }

        $blocage = null;
        $derniere = $seances ? end($seances)['date'] : null;
        if (count($seances) < self::NB_SEANCES || $derniere > $periode->date_fin->toDateString()) {
            $blocage = [
                'message' => 'La séance '.self::NB_SEANCES." dépasse la fin de la période {$periode->numero}",
                'periode_numero' => $periode->numero,
            ];
        }

        return [
            'date_premiere_session' => $premiere->toDateString(),
            'recale' => ! $premiere->equalTo($demandee),
            'seances' => $seances,
            'dates_sautees' => $sautees,
            'blocage' => $blocage,
        ];
    }

    /**
     * Aperçu sans persistance (même données que la création d'une classe).
     *
     * @param  array{annee_scolaire_id: int, periode_id: int, jour_semaine: int, date_premiere_session: string}  $data
     */
    public function preview(array $data): array
    {
        $periode = $this->periodeDeLAnnee($data);

        return $this->plan((int) $data['annee_scolaire_id'], $periode, (int) $data['jour_semaine'], $data['date_premiere_session']);
    }

    /**
     * Crée la classe et ses 14 sessions (transaction).
     *
     * @throws RegleMetierException 422 si la séance 14 dépasse la fin de la période
     */
    public function create(array $data): Classe
    {
        $periode = $this->periodeDeLAnnee($data);
        $plan = $this->plan((int) $data['annee_scolaire_id'], $periode, (int) $data['jour_semaine'], $data['date_premiere_session']);
        $this->refuserSiBloque($plan);

        return DB::transaction(function () use ($data, $plan) {
            $classe = Classe::create([
                'cours_id' => $data['cours_id'],
                'annee_scolaire_id' => $data['annee_scolaire_id'],
                'periode_id' => $data['periode_id'],
                'jour_semaine' => $data['jour_semaine'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'lieu' => $data['lieu'] ?? null,
                'date_premiere_session' => $plan['date_premiere_session'],
                'statut' => Classe::STATUT_ACTIVE,
            ]);

            $this->generateSessions($classe);

            return $classe;
        });
    }

    /**
     * Crée les séances (bis_rang 0) manquantes d'une classe existante. Idempotent : les séances
     * déjà présentes (quel que soit leur statut ou leur date) ne sont jamais recréées ni modifiées.
     *
     * @return int nombre de sessions créées
     */
    public function generateSessions(Classe $classe): int
    {
        $classe->loadMissing('periode');
        $plan = $this->plan($classe->annee_scolaire_id, $classe->periode, $classe->jour_semaine, $classe->date_premiere_session->toDateString());
        $this->refuserSiBloque($plan);

        return DB::transaction(function () use ($classe, $plan) {
            $existantes = CourseSession::query()
                ->where('classe_id', $classe->id)
                ->where('bis_rang', 0)
                ->pluck('seance_numero')
                ->all();

            $creees = [];
            foreach ($plan['seances'] as $seance) {
                if (in_array($seance['seance_numero'], $existantes, true)) {
                    continue;
                }

                $creees[] = CourseSession::create([
                    'classe_id' => $classe->id,
                    'seance_numero' => $seance['seance_numero'],
                    'bis_rang' => 0,
                    'date' => $seance['date'],
                    'heure_debut' => $classe->heure_debut,
                    'heure_fin' => $classe->heure_fin,
                    'lieu' => $classe->lieu,
                    'statut' => CourseSession::STATUT_PLANIFIEE,
                ]);
            }

            // Les professeurs actifs de la classe sont propagés aux séances créées après coup.
            $this->assignations->propagerAuxSessions($classe, $creees);

            return count($creees);
        });
    }

    private function periodeDeLAnnee(array $data): Periode
    {
        $periode = Periode::find($data['periode_id']);

        if (! $periode || $periode->annee_scolaire_id !== (int) $data['annee_scolaire_id']) {
            throw RegleMetierException::invalide(
                "Cette période n'appartient pas à l'année scolaire choisie.",
                ['periode_id' => ["Cette période n'appartient pas à l'année scolaire choisie."]]
            );
        }

        return $periode;
    }

    private function refuserSiBloque(array $plan): void
    {
        if ($plan['blocage']) {
            throw RegleMetierException::invalide(
                $plan['blocage']['message'],
                ['date_premiere_session' => [$plan['blocage']['message']]]
            );
        }
    }
}
