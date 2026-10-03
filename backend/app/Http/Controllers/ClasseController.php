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
        $query = Classe::query()->visiblePour($request->user())
            ->with(Classe::relationsResource())
            ->withCount('sessionsActives');

        foreach (['annee_scolaire_id', 'jour_semaine', 'statut'] as $champ) {
            if ($request->filled($champ)) {
                $query->where($champ, $request->input($champ));
            }
        }
        // cours_id : P1 ou P2 ; periode_id : classes ayant cette période (CLS-02).
        foreach (['cours_id', 'periode_id'] as $champ) {
            if ($request->filled($champ)) {
                $query->whereHas('periodes', fn ($q) => $q->where($champ, $request->integer($champ)));
            }
        }

        return ClasseResource::collection(
            $query->orderBy('annee_scolaire_id')->orderBy('jour_semaine')->orderBy('heure_debut')->orderBy('id')
                ->paginate((int) $request->input('per_page', 25))
        );
    }

    public function store(StoreClasseRequest $request): JsonResponse
    {
        $classe = $this->generator->create($request->validated());

        return (new ClasseResource(self::charger($classe)))->response()->setStatusCode(201);
    }

    /** Aperçu sans persistance : par période, 14 dates, dates sautées, avertissements, blocage éventuel. */
    public function apercu(StoreClasseRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->generator->preview($request->validated())]);
    }

    public function show(Classe $classe): ClasseResource
    {
        Gate::authorize('view', $classe);

        return new ClasseResource(self::charger($classe));
    }

    public function update(UpdateClasseRequest $request, Classe $classe): ClasseResource
    {
        return new ClasseResource(self::charger($this->service->modifier($classe, $request->validated())));
    }

    public function destroy(Classe $classe): JsonResponse
    {
        Gate::authorize('delete', $classe);

        $this->service->supprimer($classe);

        return response()->json(null, 204);
    }

    public static function charger(Classe $classe): Classe
    {
        return $classe->unsetRelations()->load(Classe::relationsResource())->loadCount('sessionsActives');
    }
}
