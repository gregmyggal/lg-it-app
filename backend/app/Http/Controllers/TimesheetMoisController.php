<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimesheetResource;
use App\Models\CourseSession;
use App\Models\Timesheet;
use App\Services\EmployeurMoisService;
use App\Services\TimesheetLissingService;
use App\Services\TimesheetService;
use App\Services\TimesheetSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** « Encoder mon mois » (CLS-01 T3, mock-up 02) : sessions du mois, heures libres, synthèse, soumission du mois. */
class TimesheetMoisController extends Controller
{
    public function __construct(private readonly TimesheetService $service, private readonly EmployeurMoisService $employeurs) {}

    public function monMois(Request $request): JsonResponse
    {
        Gate::authorize('create', Timesheet::class);

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $professeur = $request->user()->professeur;

        $saisies = Timesheet::where('professeur_id', $professeur->id)
            ->whereYear('date_prestation', $v['annee'])->whereMonth('date_prestation', $v['mois'])
            ->with('professeur', 'cours', 'session.classe', 'session.classePeriode.cours', 'session.classePeriode.periode')
            ->orderBy('date_prestation')->orderBy('id')->get();

        $parSession = $saisies->whereNotNull('course_session_id')->groupBy('course_session_id');

        // Sessions du mois où j'interviens (ligne session_professors, même « remplacé ») ou où j'ai des heures.
        $sessions = CourseSession::query()
            ->whereYear('date', $v['annee'])->whereMonth('date', $v['mois'])
            ->where(fn ($q) => $q
                ->whereHas('sessionProfesseurs', fn ($l) => $l->where('professeur_id', $professeur->id))
                ->orWhereIn('id', $parSession->keys()))
            ->with(['classe', 'classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur'])
            ->orderBy('date')->orderBy('heure_debut')->get()
            ->filter(fn (CourseSession $s) => $this->service->aCommence($s) || $parSession->has($s->id))
            ->values();

        $donnees = $sessions->map(function (CourseSession $s) use ($professeur, $parSession, $request) {
            $mienne = $s->sessionProfesseurs->firstWhere('professeur_id', $professeur->id);
            $remplaceur = $mienne?->remplace
                ? $s->sessionProfesseurs->firstWhere('professeur_id', $mienne->remplace_par_professeur_id)?->professeur
                : null;
            $miennes = $parSession->get($s->id, collect());

            return [
                'id' => $s->id,
                'libelle' => $s->libelle(),
                'seance_numero' => $s->seance_numero,
                'bis_rang' => $s->bis_rang,
                'date' => $s->date->toDateString(),
                'heure_debut' => substr($s->heure_debut, 0, 5),
                'heure_fin' => substr($s->heure_fin, 0, 5),
                'statut' => $s->statut,
                'annulee' => $s->isAnnulee(),
                'classe_id' => $s->classe_id,
                'classe_libelle' => $s->classePeriode->cours->titre,
                'periode_numero' => $s->classePeriode->periode->numero,
                'libelle_complet' => $s->libelleComplet(),
                'duree_par_defaut' => $this->service->dureeParDefaut($s),
                'duree_seance' => $this->service->dureeSeance($s),
                'encodage' => $this->service->etatEncodage($miennes),
                'peut_encoder' => $this->service->peutEncoder($professeur, $s),
                'remplace_par' => $remplaceur ? ['id' => $remplaceur->id, 'nom' => trim($remplaceur->prenom.' '.$remplaceur->nom)] : null,
                'mes_timesheets' => TimesheetResource::collection($miennes->values())->resolve($request),
            ];
        });

        $lissage = (new TimesheetLissingService)->calculateMonthlyMontants($professeur->id, $v['annee'], $v['mois']);

        return response()->json([
            'annee' => (int) $v['annee'],
            'mois' => (int) $v['mois'],
            // EMP-01 (RG-10) : le professeur voit le nom de l'entité de son mois, en lecture seule.
            'employeur' => ['nom' => $this->employeurs->effectif($professeur, (int) $v['annee'], (int) $v['mois'])->nom],
            'sessions' => $donnees,
            'libres' => TimesheetResource::collection($saisies->whereNull('course_session_id')->values())->resolve($request),
            'synthese' => [
                'heures' => round((float) $saisies->sum('nombre_heures'), 2),
                'montant' => round((float) $lissage['total_montant'], 2),
                'jours' => $saisies->map(fn ($t) => $t->date_prestation->toDateString())->unique()->count(),
                'depassements' => $lissage['depassements'],
                'nb_brouillons' => $saisies->where('statut_validation', Timesheet::STATUT_BROUILLON)->count(),
            ],
            'peut_signer' => (new TimesheetSignatureService)->canSignMonth($professeur->id, $v['annee'], $v['mois'])['can_sign'],
        ]);
    }

    public function soumettreMois(Request $request): JsonResponse
    {
        Gate::authorize('create', Timesheet::class);

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'sessions' => ['sometimes', 'array', 'max:100'],
            'sessions.*.course_session_id' => ['required', 'integer', 'exists:course_sessions,id'],
            'sessions.*.nombre_heures' => ['sometimes', 'nullable', 'numeric', 'min:0.5', 'max:24'],
            'sessions.*.type_activite' => ['sometimes', Rule::in(TimesheetService::TYPES)],
        ]);

        return response()->json(
            $this->service->soumettreMois($request->user(), (int) $v['annee'], (int) $v['mois'], $v['sessions'] ?? [])
        );
    }
}
