<?php

namespace App\Http\Controllers;

use App\Http\Requests\MesClassesRequest;
use App\Http\Resources\ClasseResource;
use App\Http\Resources\CourseSessionResource;
use App\Http\Resources\ProfesseurClasseResource;
use App\Http\Resources\TimesheetResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\ProfesseurClasse;
use App\Models\SessionProfesseur;
use App\Models\Timesheet;
use App\Services\CalendrierScolaireService;
use App\Services\TimesheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Portail professeur « Mes classes » : uniquement les classes et sessions du professeur connecté. */
class MesClassesController extends Controller
{
    public function __construct(
        private readonly CalendrierScolaireService $calendrier,
        private readonly TimesheetService $timesheets,
    ) {}

    public function index(MesClassesRequest $request): JsonResponse
    {
        $professeur = $request->user()->professeur;

        $assignations = $professeur->assignations()
            ->when(! $request->boolean('inclure_terminees'), fn ($q) => $q->actif())
            ->with(['classe.cours', 'classe.periode', 'classe.anneeScolaire', 'classe.prochaineSession', 'classe.assignationsActives.professeur'])
            ->orderByDesc('id')
            ->get();

        ProfesseurClasseResource::attacherCoProfesseurs($assignations);

        // Sessions à venir où le professeur intervient comme remplaçant ponctuel (sans assignation à la classe).
        $remplacements = CourseSession::query()
            ->whereHas('sessionProfesseurs', fn ($q) => $q->where('professeur_id', $professeur->id)
                ->where('origine', SessionProfesseur::ORIGINE_REMPLACEMENT)->where('remplace', false))
            ->where('date', '>=', now('Europe/Brussels')->toDateString())
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->with(['classe.cours', 'sessionProfesseurs.professeur'])
            ->orderBy('date')->orderBy('heure_debut')->get();
        $this->calendrier->attachAlerts($remplacements);

        return response()->json(['remplacements' => CourseSessionResource::collection($remplacements)->resolve($request), 'data' => $assignations->map(fn ($a) => [
            'assignation_id' => $a->id,
            'role' => $a->role,
            'date_debut' => $a->date_debut->toDateString(),
            'date_fin' => $a->date_fin?->toDateString(),
            'actif' => $a->isActif(),
            'classe' => (new ClasseResource($a->classe))->resolve(),
            'co_professeurs' => $a->getRelation('co_professeurs')
                ->map(fn ($o) => ProfesseurClasseResource::professeurLeger($o->professeur) + ['role' => $o->role])->values(),
        ])->values()]);
    }

    public function sessions(Request $request, Classe $classe): JsonResponse
    {
        Gate::authorize('viewAny', ProfesseurClasse::class);
        Gate::authorize('view', $classe);

        $moi = $request->user()->professeur->id;
        $classe->load('cours');

        $sessions = $classe->sessions()->visiblePour($request->user())
            ->with(['sessionProfesseurs.professeur'])->get();
        $sessions->each->setRelation('classe', $classe);
        $this->calendrier->attachAlerts($sessions);

        // T3 : mes propres saisies par session (jamais celles d'un autre professeur), pour l'état d'encodage.
        $mesSaisies = Timesheet::where('professeur_id', $moi)->whereIn('course_session_id', $sessions->pluck('id'))
            ->with('professeur', 'cours')->get()->groupBy('course_session_id');
        $professeur = $request->user()->professeur;

        return response()->json(['data' => $sessions->map(function (CourseSession $s) use ($moi, $mesSaisies, $professeur, $request) {
            $lignes = $s->sessionProfesseurs;
            $mienne = $lignes->firstWhere('professeur_id', $moi);
            $remplace = $lignes->firstWhere('remplace_par_professeur_id', $moi);

            $situation = match (true) {
                $mienne?->remplace === true => ['type' => 'remplace_par', 'professeur' => $this->nom($lignes->firstWhere('professeur_id', $mienne->remplace_par_professeur_id))],
                $mienne?->origine === SessionProfesseur::ORIGINE_REMPLACEMENT => ['type' => 'remplacant_de', 'professeur' => $this->nom($remplace)],
                $mienne !== null => ['type' => 'assignee', 'professeur' => null],
                default => null,
            };

            $miennes = $mesSaisies->get($s->id, collect());

            return (new CourseSessionResource($s))->resolve() + [
                'encodage' => $this->timesheets->etatEncodage($miennes),
                'mes_timesheets' => TimesheetResource::collection($miennes->values())->resolve($request),
                'duree_par_defaut' => $this->timesheets->dureeParDefaut($s),
                'duree_seance' => $this->timesheets->dureeSeance($s),
                'peut_encoder' => $this->timesheets->peutEncoder($professeur, $s),
                'ma_situation' => $situation,
                'co_professeurs' => $lignes->reject(fn ($l) => $l->professeur_id === $moi || $l->remplace)
                    ->map(fn ($l) => ProfesseurClasseResource::professeurLeger($l->professeur) + ['role' => $l->role])->values(),
            ];
        })->values()]);
    }

    private function nom(?SessionProfesseur $ligne): ?array
    {
        return $ligne?->professeur ? ProfesseurClasseResource::professeurLeger($ligne->professeur) : null;
    }
}
