<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use App\Models\Periode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service unique de génération des 14 séances hebdomadaires de chaque période d'une classe (RG-2, CLS-02).
 * - saute les dates couvertes par une entrée non masquée du calendrier scolaire ;
 * - recale la première date sur le jour de la semaine de la classe ;
 * - la 14e séance au-delà de la fin de période n'est PAS bloquante : avertissement + `hors_periode` (RG-4) ;
 * - seul blocage : une P2 qui démarre avant la fin de la P1 (RG-3) ;
 * - création transactionnelle (classe + 1 ou 2 périodes + sessions) et génération idempotente.
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
     * Calcul pur (sans écriture) du plan d'UNE période.
     *
     * @param  list<string>  $datesForcees  dates (Y-m-d) couvertes par le calendrier où la séance est maintenue (CLS-05)
     * @return array{
     *     periode_id: int,
     *     numero: int,
     *     date_premiere_session: string,
     *     recale: bool,
     *     seances: list<array{seance_numero: int, date: string, hors_periode: bool, forcee: bool, conge: ?array{libelle: string, type: string}}>,
     *     dates_sautees: list<array{date: string, libelle: string, type: string}>,
     *     avertissements: list<string>,
     *     blocage: ?array{message: string, periode_numero: int}
     * }
     */
    public function plan(int $anneeScolaireId, Periode $periode, int $jourSemaine, string $datePremiere, array $datesForcees = []): array
    {
        $demandee = Carbon::parse($datePremiere)->startOfDay();
        $courante = $demandee->copy();
        if ($courante->dayOfWeekIso !== $jourSemaine) {
            $courante->addDays(($jourSemaine - $courante->dayOfWeekIso + 7) % 7);
        }
        $premiere = $courante->copy();

        $entrees = $this->calendrier->entreesActives($anneeScolaireId);
        $fin = $periode->date_fin->toDateString();
        $seances = [];
        $sautees = [];

        for ($i = 0; $i < self::MAX_SEMAINES && count($seances) < self::NB_SEANCES; $i++) {
            $date = $courante->toDateString();
            $entree = $this->calendrier->entreeCouvrant($entrees, $date);
            $forcee = $entree !== null && in_array($date, $datesForcees, true);

            if ($entree && ! $forcee) {
                $sautees[] = ['date' => $date, 'libelle' => $entree->libelle, 'type' => $entree->type];
            } else {
                $seances[] = [
                    'seance_numero' => count($seances) + 1,
                    'date' => $date,
                    'hors_periode' => PeriodeRegles::horsPeriode($date, $fin),
                    'forcee' => $forcee,
                    'conge' => $forcee ? ['libelle' => $entree->libelle, 'type' => $entree->type] : null,
                ];
            }

            $courante->addWeek();
        }

        $avertissements = [];
        $blocage = null;
        if (count($seances) < self::NB_SEANCES) {
            $blocage = [
                'message' => 'Impossible de planifier les '.self::NB_SEANCES." séances de la période {$periode->numero} (calendrier scolaire saturé).",
                'periode_numero' => $periode->numero,
            ];
        } elseif ($seances && end($seances)['hors_periode']) {
            $avertissements[] = 'La séance '.self::NB_SEANCES." dépasse la fin de la période {$periode->numero} (".$periode->date_fin->format('d/m/Y').')';
        }

        return [
            'periode_id' => $periode->id,
            'numero' => $periode->numero,
            'date_premiere_session' => $premiere->toDateString(),
            'recale' => ! $premiere->equalTo($demandee),
            'seances' => $seances,
            'dates_sautees' => $sautees,
            'avertissements' => $avertissements,
            'blocage' => $blocage,
        ];
    }

    /**
     * Aperçu sans persistance de la création d'une classe (1 ou 2 périodes).
     *
     * @param  array{annee_scolaire_id: int, jour_semaine: int, periodes: list<array{periode_id: int, cours_id: int, date_premiere_session: string, dates_forcees?: list<string>}>}  $data
     * @return array{periodes: list<array<string, mixed>>}
     */
    public function preview(array $data): array
    {
        return ['periodes' => array_values($this->plansCreation($data))];
    }

    /**
     * Aperçu de l'ajout d'une période à une classe existante.
     *
     * @param  array{periode_id: int, cours_id?: int, date_premiere_session: string, dates_forcees?: list<string>}  $data
     * @return array{periodes: list<array<string, mixed>>}
     */
    public function previewAjout(Classe $classe, array $data): array
    {
        return ['periodes' => [$this->planAjout($classe, $data)[1]]];
    }

    /**
     * Crée la classe, ses périodes et leurs 14 sessions (transaction : tout ou rien).
     *
     * @throws RegleMetierException 422 si une date est invalide ou si la P2 démarre avant la fin de la P1
     */
    public function create(array $data): Classe
    {
        $plans = $this->plansCreation($data);
        $this->refuserSiBloque($plans, $this->indexParPeriode($data['periodes']));

        return DB::transaction(function () use ($data, $plans) {
            $classe = Classe::create([
                'annee_scolaire_id' => $data['annee_scolaire_id'],
                'jour_semaine' => $data['jour_semaine'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'lieu' => $data['lieu'] ?? null,
                'statut' => Classe::STATUT_ACTIVE,
                'source_classe_id' => $data['source_classe_id'] ?? null,
            ]);

            foreach ($data['periodes'] as $p) {
                $plan = $plans[(int) $p['periode_id']];
                $classePeriode = $classe->periodes()->create([
                    'periode_id' => $p['periode_id'],
                    'cours_id' => $p['cours_id'],
                    'date_premiere_session' => $plan['date_premiere_session'],
                    'statut' => ClassePeriode::STATUT_ACTIVE,
                ]);
                $this->generateSessions($classePeriode, $plan);
            }

            return $classe;
        });
    }

    /**
     * Ajoute une période (et ses 14 sessions) à une classe existante (transaction).
     *
     * @param  array{periode_id: int, cours_id: int, date_premiere_session: string}  $data
     *
     * @throws RegleMetierException 422 période déjà présente / date invalide / P2 avant la fin de P1
     */
    public function ajouterPeriode(Classe $classe, array $data): ClassePeriode
    {
        [$periode, $plan] = $this->planAjout($classe, $data);
        $this->refuserSiBloque([$periode->id => $plan], null);

        return DB::transaction(function () use ($classe, $data, $plan) {
            $classePeriode = $classe->periodes()->create([
                'periode_id' => $data['periode_id'],
                'cours_id' => $data['cours_id'],
                'date_premiere_session' => $plan['date_premiere_session'],
                'statut' => ClassePeriode::STATUT_ACTIVE,
            ]);
            $this->generateSessions($classePeriode, $plan);

            return $classePeriode;
        });
    }

    /**
     * Crée les séances (bis_rang 0) manquantes d'une période de classe. Idempotent : les séances
     * déjà présentes (quel que soit leur statut ou leur date) ne sont jamais recréées ni modifiées.
     *
     * @param  ?array  $plan  plan déjà calculé (sinon recalculé depuis la date de démarrage)
     * @return int nombre de sessions créées
     */
    public function generateSessions(ClassePeriode $classePeriode, ?array $plan = null): int
    {
        $classePeriode->loadMissing('classe', 'periode');
        $classe = $classePeriode->classe;
        $plan ??= $this->plan($classe->annee_scolaire_id, $classePeriode->periode, $classe->jour_semaine, $classePeriode->date_premiere_session->toDateString());

        return DB::transaction(function () use ($classe, $classePeriode, $plan) {
            $existantes = CourseSession::query()
                ->where('classe_periode_id', $classePeriode->id)
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
                    'classe_periode_id' => $classePeriode->id,
                    'seance_numero' => $seance['seance_numero'],
                    'bis_rang' => 0,
                    'date' => $seance['date'],
                    'heure_debut' => $classe->heure_debut,
                    'heure_fin' => $classe->heure_fin,
                    'lieu' => $classe->lieu,
                    'statut' => CourseSession::STATUT_PLANIFIEE,
                ]);
            }

            // Les professeurs actifs de la classe sont propagés aux séances créées.
            $this->assignations->propagerAuxSessions($classe, $creees);

            return count($creees);
        });
    }

    /** Dernière date de séance non annulée d'une période de classe (null si aucune). */
    public function derniereSeance(ClassePeriode $classePeriode): ?string
    {
        $date = CourseSession::where('classe_periode_id', $classePeriode->id)
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->max('date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * Plans de création, indexés par periode_id, triés par numéro ; le blocage « P2 avant la fin de P1 » est posé sur la P2.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plansCreation(array $data): array
    {
        $annee = (int) $data['annee_scolaire_id'];
        $this->refuserSiArchivee($annee, 'annee_scolaire_id');
        $plans = [];
        foreach (array_values($data['periodes']) as $i => $p) {
            $periode = $this->periodeDeLAnnee($annee, (int) $p['periode_id'], "periodes.{$i}.periode_id");
            $this->assertDansLesBornes($periode, $p['date_premiere_session'], "periodes.{$i}.date_premiere_session");
            $plans[$periode->id] = $this->plan($annee, $periode, (int) $data['jour_semaine'], $p['date_premiere_session'], $p['dates_forcees'] ?? []);
        }
        uasort($plans, fn ($a, $b) => $a['numero'] <=> $b['numero']);

        $precedente = null;
        foreach ($plans as &$plan) {
            if ($precedente && ! $plan['blocage'] && $precedente['seances'] && $plan['date_premiere_session'] <= end($precedente['seances'])['date']) {
                $plan['blocage'] = $this->blocagePeriode2($plan['numero'], end($precedente['seances'])['date']);
            }
            $precedente = $plan;
        }
        unset($plan);

        return $plans;
    }

    /** @return array{0: Periode, 1: array<string, mixed>} */
    private function planAjout(Classe $classe, array $data): array
    {
        $this->refuserSiArchivee($classe->annee_scolaire_id, 'periode_id');
        $periode = $this->periodeDeLAnnee($classe->annee_scolaire_id, (int) $data['periode_id'], 'periode_id');

        if ($classe->periodes()->where('periode_id', $periode->id)->exists()) {
            $message = "Cette classe a déjà la période {$periode->numero}.";
            throw RegleMetierException::invalide($message, ['periode_id' => [$message]]);
        }

        $this->assertDansLesBornes($periode, $data['date_premiere_session'], 'date_premiere_session');
        $plan = $this->plan($classe->annee_scolaire_id, $periode, $classe->jour_semaine, $data['date_premiere_session'], $data['dates_forcees'] ?? []);

        if (! $plan['blocage'] && $plan['seances']) {
            $derniere = end($plan['seances'])['date'];
            foreach ($classe->periodes()->with('periode')->get() as $autre) {
                if ($autre->periode->numero < $periode->numero) {
                    $finAutre = $this->derniereSeance($autre);
                    if ($finAutre && $plan['date_premiere_session'] <= $finAutre) {
                        $plan['blocage'] = $this->blocagePeriode2($periode->numero, $finAutre);
                    }
                } elseif ($autre->periode->numero > $periode->numero && $derniere >= $autre->date_premiere_session->toDateString()) {
                    $plan['blocage'] = $this->blocagePeriode2($autre->periode->numero, $derniere);
                }
            }
        }

        return [$periode, $plan];
    }

    private function blocagePeriode2(int $numero, string $derniereSeancePrecedente): array
    {
        return [
            'message' => "La période {$numero} démarre avant la fin de la période ".($numero - 1).' (dernière séance le '.Carbon::parse($derniereSeancePrecedente)->format('d/m/Y').').',
            'periode_numero' => $numero,
        ];
    }

    private function periodeDeLAnnee(int $anneeScolaireId, int $periodeId, string $champ): Periode
    {
        $periode = Periode::find($periodeId);

        if (! $periode || $periode->annee_scolaire_id !== $anneeScolaireId) {
            $message = "Cette période n'appartient pas à l'année scolaire choisie.";
            throw RegleMetierException::invalide($message, [$champ => [$message]]);
        }

        return $periode;
    }

    private function assertDansLesBornes(Periode $periode, string $date, string $champ): void
    {
        if ($date < $periode->date_debut->toDateString() || $date > $periode->date_fin->toDateString()) {
            $message = "La date de démarrage doit être comprise dans la période {$periode->numero} (".$periode->date_debut->format('d/m/Y').' → '.$periode->date_fin->format('d/m/Y').').';
            $periode->loadMissing('anneeScolaire');

            // Code stable + bornes : le front en tire le lien « Modifier les dates de la période N » (CLS-03 RG-8).
            throw RegleMetierException::invalide($message, [$champ => [$message]], [
                'code' => 'date_hors_bornes_periode',
                'contexte' => [
                    'annee_id' => $periode->annee_scolaire_id,
                    'annee_libelle' => $periode->anneeScolaire->libelle,
                    'numero' => $periode->numero,
                    'debut' => $periode->date_debut->toDateString(),
                    'fin' => $periode->date_fin->toDateString(),
                ],
            ]);
        }
    }

    /** @throws RegleMetierException 422 : on ne crée plus de classe ni de période sur une année archivée (CLS-03 RG-5) */
    private function refuserSiArchivee(int $anneeScolaireId, string $champ): void
    {
        if (AnneeScolaire::whereKey($anneeScolaireId)->where('statut', AnneeScolaire::STATUT_ARCHIVEE)->exists()) {
            $message = 'Cette année scolaire est archivée.';
            throw RegleMetierException::invalide($message, [$champ => [$message]], ['code' => 'annee_archivee']);
        }
    }

    /** @param array<int, array<string, mixed>> $plans indexés par periode_id */
    private function refuserSiBloque(array $plans, ?array $indexParPeriode): void
    {
        foreach ($plans as $periodeId => $plan) {
            if ($plan['blocage']) {
                $champ = $indexParPeriode !== null ? "periodes.{$indexParPeriode[$periodeId]}.date_premiere_session" : 'date_premiere_session';
                throw RegleMetierException::invalide($plan['blocage']['message'], [$champ => [$plan['blocage']['message']]]);
            }
        }
    }

    /** @return array<int, int> periode_id => index dans le payload */
    private function indexParPeriode(array $periodes): array
    {
        $index = [];
        foreach (array_values($periodes) as $i => $p) {
            $index[(int) $p['periode_id']] = $i;
        }

        return $index;
    }
}
