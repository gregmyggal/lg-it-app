<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignerProfesseurRequest;
use App\Http\Requests\ModifierAssignationRequest;
use App\Http\Requests\TerminerAssignationRequest;
use App\Http\Resources\ProfesseurClasseResource;
use App\Models\Classe;
use App\Models\Professeur;
use App\Models\ProfesseurClasse;
use App\Services\ClasseProfesseurAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Assignation des professeurs, vue depuis la classe (même service que côté professeur). */
class ClasseProfesseurController extends Controller
{
    public function __construct(private readonly ClasseProfesseurAssignmentService $service) {}

    public function index(Classe $classe): AnonymousResourceCollection
    {
        Gate::authorize('view', $classe);

        $assignations = $classe->assignations()->with('professeur')->orderBy('id')->get();
        $assignations->each->setRelation('classe', $classe);
        ProfesseurClasseResource::hydrater($assignations);

        return ProfesseurClasseResource::collection($assignations);
    }

    public function apercu(AssignerProfesseurRequest $request, Classe $classe): JsonResponse
    {
        $professeur = Professeur::findOrFail($request->integer('professeur_id'));

        return response()->json([
            'data' => $this->service->apercu($classe, $professeur, $request->safe()->only(['date_debut', 'date_fin'])),
        ]);
    }

    public function store(AssignerProfesseurRequest $request, Classe $classe): JsonResponse
    {
        $professeur = Professeur::findOrFail($request->integer('professeur_id'));

        $resultat = $this->service->assigner($classe, $professeur, $request->safe()->only(['role', 'date_debut', 'date_fin']));

        return $this->reponse($resultat, $resultat['cree'] ? 201 : 200);
    }

    public function update(ModifierAssignationRequest $request, Classe $classe, Professeur $professeur): JsonResponse
    {
        $assignation = $this->assignation($classe, $professeur);

        return $this->reponse($this->service->modifier($assignation, $request->validated()));
    }

    public function destroy(TerminerAssignationRequest $request, Classe $classe, Professeur $professeur): JsonResponse
    {
        $assignation = $this->assignation($classe, $professeur);

        return $this->reponse($this->service->terminer($assignation, $request->input('date_fin')));
    }

    private function assignation(Classe $classe, Professeur $professeur): ProfesseurClasse
    {
        return ProfesseurClasse::where('classe_id', $classe->id)
            ->where('professeur_id', $professeur->id)
            ->firstOrFail();
    }

    /** @param array{assignation: ProfesseurClasse, recapitulatif: array<string, int>} $resultat */
    private function reponse(array $resultat, int $status = 200): JsonResponse
    {
        $assignation = $resultat['assignation']->load(['professeur', 'classe.cours']);
        ProfesseurClasseResource::hydrater([$assignation]);

        return response()->json([
            'data' => (new ProfesseurClasseResource($assignation))->resolve(),
            'recapitulatif' => $resultat['recapitulatif'],
        ], $status);
    }
}
