<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Models\CourseSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * STG-01 : génération des séances d'un stage (1 à 5 demi-journées sur une semaine, lundi → vendredi).
 * Un stage est une `Classe` de type « stage » avec une unique ligne `classe_periodes` (cours, sans période scolaire).
 * - jours fériés et fermetures décochés par défaut ; recochés = avertissement non bloquant ;
 * - les congés scolaires (vacances) ne déclenchent aucune alerte : c'est le cas normal d'un stage ;
 * - numéros de séance 1..n dans l'ordre chronologique.
 */
class StageSessionGenerator
{
    public const NB_JOURS_MAX = 5;

    public const MATIN = 'matin';

    public const APRES_MIDI = 'apres_midi';

    public const DEMI_JOURNEES = [self::MATIN, self::APRES_MIDI];

    /** Horaires par défaut, modifiables par stage via `creneaux`. */
    public const CRENEAUX_DEFAUT = [
        self::MATIN => ['heure_debut' => '09:00', 'heure_fin' => '12:00'],
        self::APRES_MIDI => ['heure_debut' => '13:30', 'heure_fin' => '16:30'],
    ];

    private const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi'];

    public function __construct(private readonly CalendrierScolaireService $calendrier) {}

    /**
     * Calcul pur (sans écriture).
     *
     * @param  array{date_debut: string, demi_journee_defaut?: string, creneaux?: array, jours?: list<array{date: string, demi_journee: string}>}  $data
     * @return array{
     *     annee_scolaire_id: ?int, date_debut: string, date_fin: string,
     *     creneaux: array, jours: list<array<string, mixed>>, seances: list<array<string, mixed>>,
     *     nb_seances: int, duree_totale_minutes: int, avertissements: list<string>, blocage: ?array{message: string}
     * }
     */
    public function plan(array $data): array
    {
        $lundi = Carbon::parse($data['date_debut'])->startOfWeek();
        $creneaux = $this->creneaux($data['creneaux'] ?? []);
        $defaut = $data['demi_journee_defaut'] ?? self::MATIN;
        $annee = $this->anneeCouvrant($lundi);
        $entrees = $annee ? $this->calendrier->entreesActives($annee->id) : collect();

        $choix = null;
        if (isset($data['jours'])) {
            $choix = [];
            foreach ($data['jours'] as $j) {
                $choix[Carbon::parse($j['date'])->toDateString()] = $j['demi_journee'];
            }
        }

        $jours = [];
        $seances = [];
        $avertissements = [];
        for ($i = 1; $i <= self::NB_JOURS_MAX; $i++) {
            $date = $lundi->copy()->addDays($i - 1)->toDateString();
            $entree = $this->calendrier->entreeCouvrant($entrees, $date);
            $ferme = $entree !== null && $entree->type !== CalendrierScolaire::TYPE_VACANCES;
            $actif = $choix === null ? ! $ferme : array_key_exists($date, $choix);
            $demi = $choix[$date] ?? $defaut;

            $jours[] = [
                'date' => $date,
                'jour_semaine' => $i,
                'conge' => $entree ? ['libelle' => $entree->libelle, 'type' => $entree->type] : null,
                'ferme' => $ferme,
                'actif' => $actif,
                'demi_journee' => $demi,
            ];

            if (! $actif) {
                continue;
            }
            $seances[] = [
                'seance_numero' => count($seances) + 1,
                'date' => $date,
                'jour_semaine' => $i,
                'demi_journee' => $demi,
                'heure_debut' => $creneaux[$demi]['heure_debut'],
                'heure_fin' => $creneaux[$demi]['heure_fin'],
            ];
            if ($ferme) {
                $avertissements[] = self::JOURS[$i].' '.Carbon::parse($date)->format('d/m')." tombe sur « {$entree->libelle} » ({$this->typeLibelle($entree->type)}).";
            }
        }

        $blocage = null;
        if (! $annee) {
            $blocage = ['message' => 'Aucune année scolaire ne couvre cette semaine.'];
        } elseif ($annee->isArchivee()) {
            $blocage = ['message' => 'Cette année scolaire est archivée.'];
        } elseif (! $seances) {
            $blocage = ['message' => 'Cochez au moins un jour.'];
        }

        return [
            'annee_scolaire_id' => $annee?->id,
            'date_debut' => $lundi->toDateString(),
            'date_fin' => $lundi->copy()->addDays(self::NB_JOURS_MAX - 1)->toDateString(),
            'creneaux' => $creneaux,
            'jours' => $jours,
            'seances' => $seances,
            'nb_seances' => count($seances),
            'duree_totale_minutes' => array_sum(array_map(
                fn ($s) => (int) Carbon::parse($s['heure_debut'])->diffInMinutes(Carbon::parse($s['heure_fin'])),
                $seances
            )),
            'avertissements' => $avertissements,
            'blocage' => $blocage,
        ];
    }

    /** @throws RegleMetierException 422 si aucune année ne couvre la semaine, année archivée ou aucun jour coché */
    public function create(array $data): Classe
    {
        $plan = $this->plan($data);
        if ($plan['blocage']) {
            $champ = $plan['annee_scolaire_id'] === null ? 'date_debut' : 'jours';
            throw RegleMetierException::invalide($plan['blocage']['message'], [$champ => [$plan['blocage']['message']]]);
        }

        return DB::transaction(function () use ($data, $plan) {
            $premiere = $plan['seances'][0];
            $classe = Classe::create([
                'annee_scolaire_id' => $plan['annee_scolaire_id'],
                'type' => Classe::TYPE_STAGE,
                'jour_semaine' => $premiere['jour_semaine'],
                'heure_debut' => $premiere['heure_debut'],
                'heure_fin' => $premiere['heure_fin'],
                'lieu' => $data['lieu'] ?? null,
                'statut' => Classe::STATUT_ACTIVE,
            ]);

            $classePeriode = $classe->periodes()->create([
                'periode_id' => null,
                'cours_id' => $data['cours_id'],
                'date_premiere_session' => $premiere['date'],
                'statut' => ClassePeriode::STATUT_ACTIVE,
            ]);

            foreach ($plan['seances'] as $s) {
                CourseSession::create([
                    'classe_id' => $classe->id,
                    'classe_periode_id' => $classePeriode->id,
                    'seance_numero' => $s['seance_numero'],
                    'bis_rang' => 0,
                    'date' => $s['date'],
                    'heure_debut' => $s['heure_debut'],
                    'heure_fin' => $s['heure_fin'],
                    'lieu' => $classe->lieu,
                    'statut' => CourseSession::STATUT_PLANIFIEE,
                ]);
            }

            return $classe;
        });
    }

    /**
     * Année qui contient la semaine ; sinon (stage d'été, hors année scolaire) la dernière année commencée,
     * sinon la première à venir : son calendrier scolaire s'applique.
     */
    private function anneeCouvrant(Carbon $lundi): ?AnneeScolaire
    {
        $date = $lundi->toDateString();

        return AnneeScolaire::query()->whereDate('date_debut', '<=', $date)->orderByDesc('date_debut')->first()
            ?? AnneeScolaire::query()->orderBy('date_debut')->first();
    }

    /** @return array<string, array{heure_debut: string, heure_fin: string}> */
    private function creneaux(array $surcharge): array
    {
        $creneaux = self::CRENEAUX_DEFAUT;
        foreach (self::DEMI_JOURNEES as $demi) {
            foreach (['heure_debut', 'heure_fin'] as $champ) {
                if (! empty($surcharge[$demi][$champ])) {
                    $creneaux[$demi][$champ] = $surcharge[$demi][$champ];
                }
            }
        }

        return $creneaux;
    }

    private function typeLibelle(string $type): string
    {
        return $type === CalendrierScolaire::TYPE_FERIE ? 'jour férié' : 'fermeture';
    }
}
