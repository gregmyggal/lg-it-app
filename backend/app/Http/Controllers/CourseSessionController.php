<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSessionRequest;
use App\Http\Requests\ListSessionsRequest;
use App\Http\Requests\MoveSessionRequest;
use App\Http\Resources\CourseSessionResource;
use App\Models\CourseSession;
use App\Services\CalendrierScolaireService;
use App\Services\CourseSessionService;
use App\Services\SessionReplanificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseSessionController extends Controller
{
    public function __construct(
        private readonly CourseSessionService $service,
        private readonly CalendrierScolaireService $calendrier,
        private readonly SessionReplanificationService $replanification,
    ) {}

    /** Base du calendrier : sessions filtrées (classe, cours, période de dates, statut). */
    public function index(ListSessionsRequest $request): AnonymousResourceCollection
    {
        $query = CourseSession::query()->visiblePour($request->user())->with(['classe', 'classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur']);

        if ($request->filled('classe_id')) {
            $query->where('classe_id', $request->integer('classe_id'));
        }
        if ($request->filled('cours_id')) {
            $query->whereHas('classePeriode', fn ($q) => $q->where('cours_id', $request->integer('cours_id')));
        }
        if ($request->filled('periode_numero')) {
            $query->whereHas('classePeriode.periode', fn ($q) => $q->where('numero', $request->integer('periode_numero')));
        }
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->input('date_to'));
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->input('statut'));
        }

        $page = $query->orderBy('date')->orderBy('heure_debut')->orderBy('id')
            ->paginate((int) $request->input('per_page', 50));

        $this->calendrier->attachAlerts($page->getCollection());

        return CourseSessionResource::collection($page);
    }

    public function update(MoveSessionRequest $request, CourseSession $session): CourseSessionResource
    {
        if (! $request->boolean('decaler_suivantes')) {
            return $this->reponse($this->service->deplacer($session, $request->safe()->only(['date', 'heure_debut', 'heure_fin', 'lieu'])));
        }

        $plan = $this->replanification->appliquer($session, $request->validated());

        return $this->reponse($session->refresh())->additional([
            'replanification' => array_intersect_key($plan, array_flip(['decalees', 'avertissements', 'periodes'])),
        ]);
    }

    /** CLS-06 : aperçu sans écriture du déplacement d'une séance avec décalage des suivantes. */
    public function apercuDeplacement(MoveSessionRequest $request, CourseSession $session): JsonResponse
    {
        return response()->json(['data' => $this->replanification->apercu($session, $request->validated())]);
    }

    public function cancel(CancelSessionRequest $request, CourseSession $session): CourseSessionResource
    {
        return $this->reponse($this->service->annuler($session, $request->validated('motif_annulation')));
    }

    private function reponse(CourseSession $session): CourseSessionResource
    {
        $session->load(['classe', 'classePeriode.cours', 'classePeriode.periode']);
        $this->calendrier->attachAlerts([$session]);

        return new CourseSessionResource($session);
    }
}
