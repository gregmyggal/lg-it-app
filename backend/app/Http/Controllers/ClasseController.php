<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListClassesRequest;
use App\Http\Requests\StoreClasseRequest;
use App\Http\Requests\UpdateClasseRequest;
use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Services\ClasseService;
use App\Services\ClasseSessionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ClasseController extends Controller
{
    public function __construct(
        private readonly ClasseSessionGenerator $generator,
        private readonly ClasseService $service,
    ) {}

    public function index(ListClassesRequest $request): AnonymousResourceCollection
    {
        $query = Classe::query()->with(['cours', 'periode', 'anneeScolaire', 'prochaineSession'])
            ->withCount('sessionsActives');

        foreach (['annee_scolaire_id', 'periode_id', 'cours_id', 'jour_semaine', 'statut'] as $champ) {
            if ($request->filled($champ)) {
                $query->where($champ, $request->input($champ));
            }
        }

        return ClasseResource::collection(
            $query->orderBy('annee_scolaire_id')->orderBy('periode_id')->orderBy('jour_semaine')->orderBy('heure_debut')->orderBy('id')
                ->paginate((int) $request->input('per_page', 25))
        );
    }

    public function store(StoreClasseRequest $request): JsonResponse
    {
        $classe = $this->generator->create($request->validated());

        return (new ClasseResource($this->charger($classe)))->response()->setStatusCode(201);
    }

    /** Aperçu sans persistance : 14 dates, dates sautées, blocage éventuel. */
    public function apercu(StoreClasseRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->generator->preview($request->validated())]);
    }

    public function show(Classe $classe): ClasseResource
    {
        Gate::authorize('view', $classe);

        return new ClasseResource($this->charger($classe));
    }

    public function update(UpdateClasseRequest $request, Classe $classe): ClasseResource
    {
        return new ClasseResource($this->charger($this->service->modifier($classe, $request->validated())));
    }

    public function destroy(Classe $classe): JsonResponse
    {
        Gate::authorize('delete', $classe);

        $this->service->supprimer($classe);

        return response()->json(null, 204);
    }

    private function charger(Classe $classe): Classe
    {
        return $classe->load(['cours', 'periode', 'anneeScolaire', 'prochaineSession'])
            ->loadCount('sessionsActives');
    }
}
