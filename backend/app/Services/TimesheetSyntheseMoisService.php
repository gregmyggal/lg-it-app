<?php

namespace App\Services;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * TS-01 T2 : synthèse d'un mois par professeur (heures, montants, statut du mois calculé, alertes).
 * Lecture seule : le statut du mois n'est pas stocké, il est déduit des saisies du mois (le plus bas l'emporte).
 */
class TimesheetSyntheseMoisService
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_CONTESTE = 'conteste';

    public const STATUT_A_VALIDER = 'a_valider';

    public const STATUT_ATTENTE_PROF = 'attente_prof';

    public const STATUT_PRET_PDF = 'pret_pdf';

    public const STATUT_GENERE = 'genere';

    public function __construct(
        private readonly TimesheetParametreService $parametres,
        private readonly TarifResolver $tarifs,
        private readonly EmployeurMoisService $employeurs,
    ) {}

    /** @return array{periode: array, kpis: array, professeurs: array} */
    public function synthese(int $annee, int $mois, ?User $lecteur = null): array
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin = $debut->copy()->endOfMonth();
        $plafondJour = $this->parametres->plafondJournalier($annee);
        $plafondAn = $this->parametres->plafondAnnuel($annee);

        $saisies = Timesheet::with('professeur')
            ->whereBetween('date_prestation', [$debut->toDateString(), $fin->toDateString()])
            ->get()
            ->groupBy('professeur_id');
        $sansHeures = $this->sessionsSansHeures($debut, $fin);

        $ids = $saisies->keys()->merge($sansHeures->keys())->unique()->values();
        $professeurs = Professeur::whereIn('id', $ids)->get()->keyBy('id');
        $tarifs = ProfesseurTarif::whereIn('professeur_id', $ids)->get()->groupBy('professeur_id');
        $totauxAnnuels = $this->totauxAnnuels($ids, $annee, $tarifs);
        $audits = TimesheetAudit::whereIn('professeur_id', $ids)
            ->whereIn('timesheet_id', $saisies->flatten()->pluck('id'))
            ->get()->groupBy('professeur_id');

        // EMP-01 : employeur et verrou du mois de tous les animateurs en 4 requêtes (pas de N+1).
        $employeurs = $lecteur ? $this->employeurs->resoudre($ids, $annee, $mois) : [];
        $verrous = $lecteur ? $this->employeurs->verrous($ids, $annee, $mois) : [];

        $lignes = $ids->map(function (int $id) use ($lecteur, $employeurs, $verrous, $saisies, $sansHeures, $professeurs, $tarifs, $audits, $plafondJour, $plafondAn, $totauxAnnuels) {
            $prof = $professeurs[$id];
            $lignes = $saisies->get($id, collect());
            $tarifsProf = $tarifs->get($id, collect());

            $montants = $lignes->map(fn (Timesheet $t) => $this->montant($t, $tarifsProf));
            $parJour = [];
            foreach ($lignes as $i => $t) {
                $jour = $t->date_prestation->toDateString();
                $parJour[$jour] = ($parJour[$jour] ?? 0) + (float) $montants[$i];
            }
            $joursDepassement = collect($parJour)->filter(fn ($m) => round($m, 2) > $plafondJour)->count();

            $alertes = [];
            if ($joursDepassement > 0) {
                $alertes[] = ['code' => 'depassement', 'nombre' => $joursDepassement, 'libelle' => "{$joursDepassement} jour(s) au-delà de ".number_format($plafondJour, 2, ',', ' ').' €'];
            }
            $nbSans = $sansHeures->get($id, 0);
            if ($nbSans > 0) {
                $alertes[] = ['code' => 'sessions_sans_heures', 'nombre' => $nbSans, 'libelle' => "{$nbSans} session(s) sans heures"];
            }
            if (blank($prof->compte_bancaire)) {
                $alertes[] = ['code' => 'compte_bancaire_manquant', 'nombre' => 1, 'libelle' => 'Compte bancaire manquant'];
            }
            $sansTarif = $lignes->filter(fn ($t, $i) => $montants[$i] === null)->count();
            if ($sansTarif > 0) {
                $alertes[] = ['code' => 'tarif_absent', 'nombre' => $sansTarif, 'libelle' => "{$sansTarif} ligne(s) sans tarif"];
            }
            if (($totauxAnnuels[$id] ?? 0) > $plafondAn) {
                $alertes[] = ['code' => 'plafond_annuel', 'nombre' => 1, 'libelle' => 'Plafond annuel de '.number_format($plafondAn, 2, ',', ' ').' € dépassé'];
            }

            $auditsProf = $audits->get($id, collect());
            $dernier = collect([$auditsProf->max('created_at'), $lignes->max('validated_at'), $lignes->max('signature_professeur')])->filter()->max();

            return [
                'professeur_id' => $id,
                'professeur' => trim($prof->prenom.' '.$prof->nom),
                'statut_mois' => $this->statutMois($lignes),
                'heures_animation' => round((float) $lignes->whereIn('type_activite', ['animation', 'cours'])->sum('nombre_heures'), 2),
                'heures_preparation' => round((float) $lignes->where('type_activite', 'preparation')->sum('nombre_heures'), 2),
                'total_eur' => round((float) $montants->sum(), 2),
                'lignes' => $lignes->count(),
                'lignes_soumises' => $lignes->where('statut_validation', Timesheet::STATUT_SOUMIS)->count(),
                'lignes_ajustees' => $auditsProf->pluck('timesheet_id')->unique()->count(),
                'alertes' => $alertes,
                'derniere_action_at' => $dernier ? Carbon::parse($dernier)->toIso8601String() : null,
            ] + ($lecteur ? ['employeur' => $this->employeurs->vue($employeurs[$id], $verrous[$id] ?? null, $lecteur)] : []);
        })->sortBy('professeur', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return [
            'periode' => ['annee' => $annee, 'mois' => $mois, 'plafond_journalier_eur' => $plafondJour, 'plafond_annuel_eur' => $plafondAn],
            'kpis' => [
                'soumis' => $lignes->filter(fn ($l) => $l['statut_mois'] !== self::STATUT_BROUILLON)->count(),
                'a_valider' => $lignes->where('statut_mois', self::STATUT_A_VALIDER)->count(),
                'attente_prof' => $lignes->where('statut_mois', self::STATUT_ATTENTE_PROF)->count(),
                'conteste' => $lignes->where('statut_mois', self::STATUT_CONTESTE)->count(),
                'pret_pdf' => $lignes->where('statut_mois', self::STATUT_PRET_PDF)->count(),
                'generes' => $lignes->where('statut_mois', self::STATUT_GENERE)->count(),
            ],
            'professeurs' => $lignes->all(),
        ];
    }

    /** Le statut le plus bas des saisies du mois ; sans saisie : brouillon (le professeur n'a rien soumis). */
    public function statutMois(Collection $lignes): string
    {
        if ($lignes->contains('statut_validation', Timesheet::STATUT_CONTESTE)) {
            return self::STATUT_CONTESTE;
        }
        if ($lignes->isEmpty() || $lignes->contains('statut_validation', Timesheet::STATUT_BROUILLON)) {
            return self::STATUT_BROUILLON;
        }
        if ($lignes->contains('statut_validation', Timesheet::STATUT_SOUMIS)) {
            return self::STATUT_A_VALIDER;
        }
        if ($lignes->contains('statut_validation', Timesheet::STATUT_CONFIRME)) {
            return $lignes->where('statut_validation', Timesheet::STATUT_CONFIRME)->contains(fn ($t) => $t->signature_professeur === null)
                ? self::STATUT_ATTENTE_PROF
                : self::STATUT_PRET_PDF;
        }

        return self::STATUT_GENERE;
    }

    private function montant(Timesheet $t, Collection $tarifs): ?float
    {
        return $this->tarifs->montant($t, $tarifs);
    }

    /** @return array<int, float> total des montants de l'année civile par professeur */
    private function totauxAnnuels(Collection $ids, int $annee, Collection $tarifs): array
    {
        return Timesheet::whereIn('professeur_id', $ids)
            ->whereBetween('date_prestation', ["{$annee}-01-01", "{$annee}-12-31"])
            ->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)
            ->get()
            ->groupBy('professeur_id')
            ->map(fn ($l, $id) => $l->sum(fn (Timesheet $t) => (float) $this->montant($t, $tarifs->get($id, collect()))))
            ->all();
    }

    /** @return Collection<int, int> professeur_id => nombre de sessions terminées du mois sans aucune saisie de sa part */
    private function sessionsSansHeures(Carbon $debut, Carbon $fin): Collection
    {
        $maintenant = Carbon::now('Europe/Brussels');
        $compteur = collect();

        CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->where(fn ($q) => $q->where('date', '<', $maintenant->toDateString())
                ->orWhere('statut', CourseSession::STATUT_TERMINEE)
                ->orWhere(fn ($j) => $j->where('date', $maintenant->toDateString())->where('heure_fin', '<=', $maintenant->format('H:i:s'))))
            ->with(['sessionProfesseurs' => fn ($l) => $l->where('remplace', false), 'timesheets:id,course_session_id,professeur_id'])
            ->get()
            ->each(function (CourseSession $s) use ($compteur) {
                $encode = $s->timesheets->pluck('professeur_id')->all();
                foreach ($s->sessionProfesseurs as $l) {
                    if (! in_array($l->professeur_id, $encode, true)) {
                        $compteur[$l->professeur_id] = ($compteur[$l->professeur_id] ?? 0) + 1;
                    }
                }
            });

        return $compteur;
    }
}
