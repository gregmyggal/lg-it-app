<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBisRequest;
use App\Http\Resources\CourseSessionResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Services\CalendrierScolaireService;
use App\Services\CourseSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClasseSessionController extends Controller
{
    public function __construct(
        private readonly CourseSessionService $service,
        private readonly CalendrierScolaireService $calendrier,
    ) {}

    /** Toutes les sessions d'une classe (bis et annulées incluses). */
    public function index(Request $request, Classe $classe): AnonymousResourceCollection
    {
        Gate::authorize('view', $classe);
        Gate::authorize('viewAny', CourseSession::class);

        $sessions = $classe->sessions()->visiblePour($request->user())
            ->with(['classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur'])
            ->when($request->filled('periode_numero'), fn ($q) => $q->whereHas('classePeriode.periode', fn ($p) => $p->where('numero', $request->integer('periode_numero'))))
            ->get()
            ->sortBy([
                fn (CourseSession $a, CourseSession $b) => $a->classePeriode->periode->numero <=> $b->classePeriode->periode->numero,
                ['seance_numero', 'asc'],
                ['bis_rang', 'asc'],
            ])->values();
        $sessions->each->setRelation('classe', $classe);
        $this->calendrier->attachAlerts($sessions);

        return CourseSessionResource::collection($sessions);
    }

    public function bis(StoreBisRequest $request, Classe $classe): JsonResponse
    {
        $session = $this->service->ajouterBis(
            $classe,
            $request->safe()->except('confirmer_depassement'),
            $request->boolean('confirmer_depassement')
        );

        $session->setRelation('classe', $classe);
        $session->load(['classePeriode.cours', 'classePeriode.periode']);
        $this->calendrier->attachAlerts([$session]);

        return (new CourseSessionResource($session))->response()->setStatusCode(201);
    }
}
