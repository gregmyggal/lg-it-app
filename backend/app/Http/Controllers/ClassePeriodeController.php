<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnnulerClassePeriodeRequest;
use App\Http\Requests\StoreClassePeriodeRequest;
use App\Http\Requests\UpdateClassePeriodeRequest;
use App\Http\Resources\ClasseResource;
use App\Models\Classe;
use App\Models\ClassePeriode;
use App\Services\ClassePeriodeService;
use App\Services\ClasseSessionGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Périodes d'une classe (CLS-02) : aperçu, ajout, changement de cours, suppression, annulation. */
class ClassePeriodeController extends Controller
{
    public function __construct(
        private readonly ClassePeriodeService $service,
        private readonly ClasseSessionGenerator $generator,
    ) {}

    public function apercu(StoreClassePeriodeRequest $request, Classe $classe): JsonResponse
    {
        return response()->json(['data' => $this->generator->previewAjout($classe, $request->validated())]);
    }

    public function store(StoreClassePeriodeRequest $request, Classe $classe): JsonResponse
    {
        $this->service->ajouter($classe, $request->validated());

        return (new ClasseResource(ClasseController::charger($classe)))->response()->setStatusCode(201);
    }

    public function update(UpdateClassePeriodeRequest $request, Classe $classe, ClassePeriode $classePeriode): ClasseResource
    {
        $this->appartient($classe, $classePeriode);
        $this->service->changerCours($classePeriode, $request->integer('cours_id'), $request->user()->id);

        return new ClasseResource(ClasseController::charger($classe));
    }

    public function destroy(Classe $classe, ClassePeriode $classePeriode): JsonResponse
    {
        Gate::authorize('update', $classe);
        $this->appartient($classe, $classePeriode);

        $this->service->supprimer($classePeriode);

        return response()->json(null, 204);
    }

    public function historiqueCours(Classe $classe, ClassePeriode $classePeriode): JsonResponse
    {
        Gate::authorize('update', $classe);
        $this->appartient($classe, $classePeriode);

        $lignes = $classePeriode->historiqueCours()->with(['ancienCours:id,titre', 'nouveauCours:id,titre', 'auteur:id,name'])->get();

        return response()->json(['data' => $lignes->map(fn ($l) => [
            'id' => $l->id,
            'ancien_cours' => ['id' => $l->ancienCours->id, 'titre' => $l->ancienCours->titre],
            'nouveau_cours' => ['id' => $l->nouveauCours->id, 'titre' => $l->nouveauCours->titre],
            'par' => $l->auteur ? ['id' => $l->auteur->id, 'name' => $l->auteur->name] : null,
            'date' => $l->created_at?->toIso8601String(),
        ])->values()]);
    }

    public function annuler(AnnulerClassePeriodeRequest $request, Classe $classe, ClassePeriode $classePeriode): ClasseResource
    {
        $this->appartient($classe, $classePeriode);
        $this->service->annuler($classePeriode, $request->validated('motif'));

        return new ClasseResource(ClasseController::charger($classe));
    }

    private function appartient(Classe $classe, ClassePeriode $classePeriode): void
    {
        abort_unless($classePeriode->classe_id === $classe->id, 404);
    }
}
