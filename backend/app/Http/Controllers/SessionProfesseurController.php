<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemplacerProfesseurRequest;
use App\Http\Resources\CourseSessionResource;
use App\Http\Resources\SessionProfesseurResource;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Services\CalendrierScolaireService;
use App\Services\SessionReplacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Professeurs d'une session et remplacement ponctuel (RG-9). */
class SessionProfesseurController extends Controller
{
    public function __construct(
        private readonly SessionReplacementService $remplacements,
        private readonly CalendrierScolaireService $calendrier,
    ) {}

    public function index(CourseSession $session): AnonymousResourceCollection
    {
        Gate::authorize('view', $session);

        return SessionProfesseurResource::collection(
            $session->sessionProfesseurs()->with(['professeur', 'remplacePar'])->get()
        );
    }

    public function remplacer(RemplacerProfesseurRequest $request, CourseSession $session): JsonResponse
    {
        $resultat = $this->remplacements->remplacer(
            $session,
            $request->integer('professeur_remplace_id'),
            $request->integer('professeur_remplacant_id')
        );

        return response()->json([
            'data' => $this->session($resultat['session']),
            'avertissements' => $resultat['avertissements'],
        ]);
    }

    public function annulerRemplacement(CourseSession $session, Professeur $professeur): JsonResponse
    {
        Gate::authorize('replace', $session);

        return response()->json(['data' => $this->session($this->remplacements->annuler($session, $professeur->id))]);
    }

    /** @return array<string, mixed> */
    private function session(CourseSession $session): array
    {
        $session->load(['classe.cours', 'sessionProfesseurs.professeur', 'sessionProfesseurs.remplacePar']);
        $this->calendrier->attachAlerts([$session]);

        return (new CourseSessionResource($session))->resolve();
    }
}
