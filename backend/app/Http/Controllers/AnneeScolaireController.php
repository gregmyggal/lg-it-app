<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnneeScolaireRequest;
use App\Http\Requests\UpdateAnneeScolaireRequest;
use App\Http\Resources\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use App\Services\AnneeScolaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnneeScolaireController extends Controller
{
    public function __construct(private readonly AnneeScolaireService $service) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AnneeScolaire::class);

        return AnneeScolaireResource::collection(
            AnneeScolaire::with('periodes')->orderByDesc('date_debut')->get()
        );
    }

    public function store(StoreAnneeScolaireRequest $request): JsonResponse
    {
        $annee = $this->service->creer($request->validated());

        return (new AnneeScolaireResource($annee))->response()->setStatusCode(201);
    }

    public function show(AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('view', $annee);

        return new AnneeScolaireResource($annee->load('periodes'));
    }

    public function update(UpdateAnneeScolaireRequest $request, AnneeScolaire $annee): AnneeScolaireResource
    {
        return new AnneeScolaireResource($this->service->modifier($annee, $request->validated()));
    }

    public function destroy(AnneeScolaire $annee): JsonResponse
    {
        Gate::authorize('delete', $annee);

        $this->service->supprimer($annee);

        return response()->json(null, 204);
    }
}
