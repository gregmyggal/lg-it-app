<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBisRequest;
use App\Http\Resources\CourseSessionResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Services\CalendrierScolaireService;
use App\Services\CourseSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClasseSessionController extends Controller
{
    public function __construct(
        private readonly CourseSessionService $service,
        private readonly CalendrierScolaireService $calendrier,
    ) {}

    /** Toutes les sessions d'une classe (bis et annulées incluses). */
    public function index(Classe $classe): AnonymousResourceCollection
    {
        Gate::authorize('view', $classe);
        Gate::authorize('viewAny', CourseSession::class);

        $sessions = $classe->sessions()->get();
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
        $this->calendrier->attachAlerts([$session]);

        return (new CourseSessionResource($session))->response()->setStatusCode(201);
    }
}
