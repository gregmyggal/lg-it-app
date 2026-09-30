<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendrierEntreeRequest;
use App\Http\Requests\UpdateCalendrierEntreeRequest;
use App\Http\Resources\CalendrierScolaireResource;
use App\Models\AnneeScolaire;
use App\Models\CalendrierScolaire;
use App\Services\CalendrierScolaireService;
use App\Services\FwbCalendarImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CalendrierScolaireController extends Controller
{
    public function __construct(private readonly CalendrierScolaireService $service) {}

    public function index(Request $request, AnneeScolaire $annee): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CalendrierScolaire::class);

        $filtres = $request->validate([
            'type' => ['sometimes', Rule::in(CalendrierScolaire::TYPES)],
            'source' => ['sometimes', Rule::in(CalendrierScolaire::SOURCES)],
            'avec_masques' => ['sometimes', 'boolean'],
        ]);

        $query = $annee->calendrier();
        if (! $request->boolean('avec_masques')) {
            $query->actives();
        }
        foreach (['type', 'source'] as $champ) {
            if (isset($filtres[$champ])) {
                $query->where($champ, $filtres[$champ]);
            }
        }

        return CalendrierScolaireResource::collection($query->get());
    }

    public function store(StoreCalendrierEntreeRequest $request, AnneeScolaire $annee): JsonResponse
    {
        $entree = $this->service->creer($annee, $request->validated());

        return (new CalendrierScolaireResource($entree))->response()->setStatusCode(201);
    }

    public function update(UpdateCalendrierEntreeRequest $request, CalendrierScolaire $entree): CalendrierScolaireResource
    {
        return new CalendrierScolaireResource($this->service->modifier($entree, $request->validated()));
    }

    /** Entrée FWB : masquée (200) ; entrée école : supprimée (200, masque=false). */
    public function destroy(CalendrierScolaire $entree): JsonResponse
    {
        Gate::authorize('delete', $entree);

        $masque = $this->service->supprimer($entree);

        return response()->json([
            'message' => $masque ? 'Entrée FWB masquée.' : 'Entrée supprimée.',
            'masque' => $masque,
        ]);
    }

    public function importFwb(AnneeScolaire $annee, FwbCalendarImporter $importer): JsonResponse
    {
        Gate::authorize('importFwb', CalendrierScolaire::class);

        $resultat = $importer->importer($annee);

        return response()->json([
            'message' => "Import FWB terminé : {$resultat['creees']} entrée(s) créée(s), {$resultat['ignorees']} ignorée(s).",
        ] + $resultat);
    }
}
