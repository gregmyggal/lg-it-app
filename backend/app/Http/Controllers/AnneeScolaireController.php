<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApercuImpactAnneeRequest;
use App\Http\Requests\PropositionAnneeRequest;
use App\Http\Requests\StoreAnneeScolaireRequest;
use App\Http\Requests\UpdateAnneeScolaireRequest;
use App\Http\Resources\AnneeScolaireResource;
use App\Models\AnneeScolaire;
use App\Services\AnneeImpactService;
use App\Services\AnneePeriodesRegles;
use App\Services\AnneePropositionService;
use App\Services\AnneeScolaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AnneeScolaireController extends Controller
{
    public function __construct(private readonly AnneeScolaireService $service) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AnneeScolaire::class);

        return AnneeScolaireResource::collection(AnneeScolaire::avecCompteurs()->orderByDesc('date_debut')->get());
    }

    public function proposition(PropositionAnneeRequest $request, AnneePropositionService $proposition): JsonResponse
    {
        return response()->json(['data' => $proposition->proposer($request->input('libelle'))]);
    }

    public function store(StoreAnneeScolaireRequest $request): JsonResponse
    {
        $annee = $this->service->creer($request->validated(), $request->user()->id);

        return $this->reponse($annee)->response()->setStatusCode(201);
    }

    public function show(AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('view', $annee);

        return new AnneeScolaireResource($this->recharger($annee));
    }

    public function update(UpdateAnneeScolaireRequest $request, AnneeScolaire $annee): AnneeScolaireResource
    {
        $modifiee = $this->service->modifier($annee, $request->validated(), $request->user()->id);

        return $this->reponse($modifiee);
    }

    /** Aperçu d'impact en lecture seule : aucune écriture. */
    public function apercuImpact(ApercuImpactAnneeRequest $request, AnneeScolaire $annee, AnneeImpactService $impact): JsonResponse
    {
        return response()->json(['data' => $impact->apercu($annee, $request->validated('periodes'))]);
    }

    public function archiver(Request $request, AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('archiver', $annee);

        return new AnneeScolaireResource($this->recharger($this->service->archiver($annee, $request->user()->id)));
    }

    public function reactiver(Request $request, AnneeScolaire $annee): AnneeScolaireResource
    {
        Gate::authorize('archiver', $annee);

        return new AnneeScolaireResource($this->recharger($this->service->reactiver($annee, $request->user()->id)));
    }

    public function destroy(AnneeScolaire $annee): JsonResponse
    {
        Gate::authorize('delete', $annee);

        $this->service->supprimer($annee);

        return response()->json(null, 204);
    }

    private function recharger(AnneeScolaire $annee): AnneeScolaire
    {
        return AnneeScolaire::avecCompteurs()->findOrFail($annee->id);
    }

    /** Ressource à jour + avertissements non bloquants (trou entre P1 et P2 supérieur à 6 semaines). */
    private function reponse(AnneeScolaire $annee): AnneeScolaireResource
    {
        $courante = $this->recharger($annee);
        $periodes = $courante->periodes->map(fn ($p) => [
            'numero' => $p->numero, 'date_debut' => $p->date_debut->toDateString(), 'date_fin' => $p->date_fin->toDateString(),
        ])->all();

        return (new AnneeScolaireResource($courante))
            ->additional(['avertissements' => app(AnneePeriodesRegles::class)->avertissements($periodes)]);
    }
}
