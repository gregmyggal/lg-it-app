<?php

namespace App\Services;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;

/**
 * « Aujourd'hui » du professeur (UI-M3) : prochaine séance, séances du mois à encoder en un geste, progression du mois.
 * Lecture seule : l'encodage passe par `POST /timesheets` (TimesheetService::creer) qui garde toutes les règles.
 */
class MonAccueilService
{
    public function __construct(private readonly TimesheetService $timesheets) {}

    /** @return array<string, mixed> */
    public function pour(Professeur $professeur): array
    {
        $maintenant = Carbon::now('Europe/Brussels');
        $annee = $maintenant->year;
        $mois = $maintenant->month;

        $saisies = Timesheet::where('professeur_id', $professeur->id)
            ->whereYear('date_prestation', $annee)->whereMonth('date_prestation', $mois)->get();
        $saisiesParSession = $saisies->whereNotNull('course_session_id')->groupBy('course_session_id');
        $moisSoumis = $saisies->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)->isNotEmpty();

        // Séances du mois où j'interviens sans être remplacé (annulées exclues).
        $duMois = CourseSession::query()
            ->whereYear('date', $annee)->whereMonth('date', $mois)
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereHas('sessionProfesseurs', fn ($l) => $l->where('professeur_id', $professeur->id)->where('remplace', false))
            ->with(['classe', 'classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur'])
            ->orderBy('date')->orderBy('heure_debut')->get();

        $aEncoder = $moisSoumis ? collect() : $duMois
            ->filter(fn (CourseSession $s) => $this->timesheets->aCommence($s) && ! $saisiesParSession->has($s->id))
            ->sortByDesc(fn (CourseSession $s) => $s->date->toDateString().' '.$s->heure_debut)
            ->values();

        $lissage = (new TimesheetLissingService)->calculateMonthlyMontants($professeur->id, $annee, $mois);

        return [
            'date' => $maintenant->toDateString(),
            'annee' => $annee,
            'mois' => $mois,
            'mois_soumis' => $moisSoumis,
            'prochaine_seance' => $this->prochaineSeance($professeur, $maintenant),
            'a_encoder' => $aEncoder->map(fn (CourseSession $s) => $this->carte($s, $professeur))->all(),
            'progression' => [
                'seances_total' => $duMois->count(),
                'seances_encodees' => $duMois->filter(fn (CourseSession $s) => $saisiesParSession->has($s->id))->count(),
                'heures' => round((float) $saisies->sum('nombre_heures'), 2),
                'montant' => round((float) $lissage['total_montant'], 2),
            ],
        ];
    }

    /** Première séance (non annulée, où je ne suis pas remplacé) qui n'est pas terminée : à venir, ou en cours. */
    private function prochaineSeance(Professeur $professeur, Carbon $maintenant): ?array
    {
        $session = CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereHas('sessionProfesseurs', fn ($l) => $l->where('professeur_id', $professeur->id)->where('remplace', false))
            ->where(fn ($q) => $q
                ->where('date', '>', $maintenant->toDateString())
                ->orWhere(fn ($j) => $j->where('date', $maintenant->toDateString())->where('heure_fin', '>', $maintenant->format('H:i:s'))))
            ->with(['classe', 'classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur'])
            ->orderBy('date')->orderBy('heure_debut')
            ->first();

        if (! $session) {
            return null;
        }

        $remplace = $session->sessionProfesseurs->firstWhere('remplace_par_professeur_id', $professeur->id)?->professeur;

        return $this->carte($session, $professeur) + [
            'lieu' => $session->classe?->lieu,
            'cours_id' => $session->classePeriode?->cours_id,
            'est_aujourdhui' => $session->date->toDateString() === $maintenant->toDateString(),
            'en_cours' => $this->timesheets->aCommence($session),
            'remplace' => $remplace ? $this->nom($remplace) : null,
            'co_professeurs' => $session->sessionProfesseurs
                ->where('remplace', false)->where('professeur_id', '!=', $professeur->id)
                ->map(fn ($l) => $this->nom($l->professeur))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function carte(CourseSession $s, Professeur $professeur): array
    {
        return [
            'id' => $s->id,
            'libelle' => $s->libelle(),
            'libelle_complet' => $s->libelleComplet(),
            'classe_id' => $s->classe_id,
            'classe_libelle' => $s->classePeriode->cours->titre,
            'date' => $s->date->toDateString(),
            'heure_debut' => substr($s->heure_debut, 0, 5),
            'heure_fin' => substr($s->heure_fin, 0, 5),
            'duree_par_defaut' => $this->timesheets->dureeParDefaut($s),
            'duree_seance' => $this->timesheets->dureeSeance($s),
            'peut_encoder' => $this->timesheets->peutEncoder($professeur, $s),
        ];
    }

    private function nom(Professeur $p): string
    {
        return trim($p->prenom.' '.$p->nom);
    }
}
