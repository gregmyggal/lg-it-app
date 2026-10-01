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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Assignation des classes, vue depuis le professeur (même service que côté classe : même résultat). */
class ProfesseurClasseController extends Controller
{
    public function __construct(private readonly ClasseProfesseurAssignmentService $service) {}

    /** Staff, ou le professeur lui-même. */
    public function index(Request $request, Professeur $professeur): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless(
            $user->isStaff() || ($user->isProfesseur() && $user->professeur?->id === $professeur->id),
            403,
            'Action non autorisée.'
        );

        $assignations = $professeur->assignations()->with(['professeur', 'classe.cours'])->orderByDesc('id')->get();
        ProfesseurClasseResource::hydrater($assignations);
        ProfesseurClasseResource::attacherCoProfesseurs($assignations, false);

        return ProfesseurClasseResource::collection($assignations);
    }

    public function apercu(AssignerProfesseurRequest $request, Professeur $professeur): JsonResponse
    {
        $classe = Classe::findOrFail($request->integer('classe_id'));

        return response()->json([
            'data' => $this->service->apercu($classe, $professeur, $request->safe()->only(['date_debut', 'date_fin'])),
        ]);
    }

    public function store(AssignerProfesseurRequest $request, Professeur $professeur): JsonResponse
    {
        $classe = Classe::findOrFail($request->integer('classe_id'));

        $resultat = $this->service->assigner($classe, $professeur, $request->safe()->only(['role', 'date_debut', 'date_fin']));

        return $this->reponse($resultat, $resultat['cree'] ? 201 : 200);
    }

    public function update(ModifierAssignationRequest $request, Professeur $professeur, Classe $classe): JsonResponse
    {
        return $this->reponse($this->service->modifier($this->assignation($classe, $professeur), $request->validated()));
    }

    public function destroy(TerminerAssignationRequest $request, Professeur $professeur, Classe $classe): JsonResponse
    {
        return $this->reponse($this->service->terminer($this->assignation($classe, $professeur), $request->input('date_fin')));
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
