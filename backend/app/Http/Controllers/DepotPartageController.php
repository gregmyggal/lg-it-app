<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepotPartageRequest;
use App\Http\Resources\DepotPartageResource;
use App\Models\Classe;
use App\Models\DepotPartage;
use App\Services\DepotPartageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** DEP-01 T2 : liens parents d'un stage (staff et professeurs du stage). Le lien en clair n'est renvoyé que par ces actions. */
class DepotPartageController extends Controller
{
    public function __construct(private readonly DepotPartageService $partages) {}

    public function index(Classe $classe): JsonResponse
    {
        $this->autoriser($classe);

        $liste = DepotPartage::where('classe_id', $classe->id)->withCount('fichiers')->with('createur:id,name')
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return DepotPartageResource::collection($liste)->response();
    }

    public function store(StoreDepotPartageRequest $request, Classe $classe): JsonResponse
    {
        $this->autoriser($classe);
        $partage = $this->partages->creer($classe, $request->validated('fichier_ids'), $request->user());

        return $this->reponse($partage, 201);
    }

    public function regenerer(Classe $classe, DepotPartage $partage): JsonResponse
    {
        $this->autoriser($classe, $partage);

        return $this->reponse($this->partages->regenerer($partage));
    }

    public function revoquer(Classe $classe, DepotPartage $partage): JsonResponse
    {
        $this->autoriser($classe, $partage);

        return $this->reponse($this->partages->revoquer($partage), 200, false);
    }

    public function lien(Classe $classe, DepotPartage $partage): JsonResponse
    {
        $this->autoriser($classe, $partage);
        $lien = $this->partages->lien($partage);
        abort_if($lien === null, 410, 'Ce lien n\'est plus valable. Régénérez-le pour en obtenir un nouveau.');

        return response()->json(['lien' => $lien]);
    }

    private function reponse(DepotPartage $partage, int $statut = 200, bool $avecLien = true): JsonResponse
    {
        $partage->load('createur:id,name')->loadCount('fichiers');
        $corps = ['data' => new DepotPartageResource($partage)];
        if ($avecLien) {
            $corps['lien'] = $this->partages->lien($partage);
        }

        return response()->json($corps, $statut);
    }

    private function autoriser(Classe $classe, ?DepotPartage $partage = null): void
    {
        abort_unless($classe->isStage(), 404);
        Gate::authorize('deposer', $classe);
        abort_if($partage !== null && $partage->classe_id !== $classe->id, 404);
    }
}
