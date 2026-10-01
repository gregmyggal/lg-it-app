<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSessionRequest;
use App\Http\Requests\ListSessionsRequest;
use App\Http\Requests\MoveSessionRequest;
use App\Http\Resources\CourseSessionResource;
use App\Models\CourseSession;
use App\Services\CalendrierScolaireService;
use App\Services\CourseSessionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseSessionController extends Controller
{
    public function __construct(
        private readonly CourseSessionService $service,
        private readonly CalendrierScolaireService $calendrier,
    ) {}

    /** Base du calendrier : sessions filtrées (classe, cours, période de dates, statut). */
    public function index(ListSessionsRequest $request): AnonymousResourceCollection
    {
        $query = CourseSession::query()->visiblePour($request->user())->with(['classe.cours', 'sessionProfesseurs.professeur']);

        if ($request->filled('classe_id')) {
            $query->where('classe_id', $request->integer('classe_id'));
        }
        if ($request->filled('cours_id')) {
            $query->whereHas('classe', fn ($q) => $q->where('cours_id', $request->integer('cours_id')));
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
        return $this->reponse($this->service->deplacer($session, $request->validated()));
    }

    public function cancel(CancelSessionRequest $request, CourseSession $session): CourseSessionResource
    {
        return $this->reponse($this->service->annuler($session, $request->validated('motif_annulation')));
    }

    private function reponse(CourseSession $session): CourseSessionResource
    {
        $session->load('classe.cours');
        $this->calendrier->attachAlerts([$session]);

        return new CourseSessionResource($session);
    }
}
