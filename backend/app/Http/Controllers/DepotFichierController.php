<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepotFichiersRequest;
use App\Http\Resources\DepotFichierResource;
use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\DepotEspace;
use App\Models\DepotFichier;
use App\Services\DepotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** DEP-01 : réalisations d'enfants déposées sur un stage (stage entier ou séance). Staff et professeurs du stage. */
class DepotFichierController extends Controller
{
    public function __construct(private readonly DepotService $depot) {}

    public function index(Classe $classe): JsonResponse
    {
        $this->autoriser($classe);

        $espaces = DepotEspace::where('classe_id', $classe->id)->pluck('id');
        $fichiers = DepotFichier::whereIn('depot_espace_id', $espaces)
            ->with(['espace.session:id,seance_numero,date', 'deposePar:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return DepotFichierResource::collection($fichiers)->additional(['meta' => [
            'taille_max_octets' => config('depot.taille_max_octets'),
            'quota_espace_octets' => config('depot.quota_espace_octets'),
            'extensions' => array_keys(config('depot.types')),
            'seances' => $classe->sessionsActives()->orderBy('date')->orderBy('heure_debut')
                ->get(['id', 'seance_numero', 'date'])
                ->map(fn (CourseSession $s) => ['id' => $s->id, 'seance_numero' => $s->seance_numero, 'date' => $s->date->toDateString()]),
        ]])->response();
    }

    public function store(StoreDepotFichiersRequest $request, Classe $classe): JsonResponse
    {
        $this->autoriser($classe);

        $session = $request->filled('course_session_id') ? CourseSession::find($request->integer('course_session_id')) : null;
        $resultat = $this->depot->deposer($classe, $session, $request->file('fichiers'), $request->input('libelle'), $request->user());

        $deposes = collect($resultat['deposes'])->each->load(['espace.session:id,seance_numero,date', 'deposePar:id,name']);
        $corps = [
            'data' => DepotFichierResource::collection($deposes),
            'refuses' => $resultat['refuses'],
        ];
        if ($deposes->isEmpty()) {
            return response()->json($corps + ['message' => 'Aucun fichier n\'a été déposé.'], 422);
        }

        return response()->json($corps, 201);
    }

    public function destroy(Classe $classe, DepotFichier $fichier): JsonResponse
    {
        $this->autoriser($classe, $fichier);
        $this->depot->supprimer($fichier);

        return response()->json(null, 204);
    }

    public function telecharger(Classe $classe, DepotFichier $fichier): StreamedResponse
    {
        $this->autoriser($classe, $fichier);
        abort_unless($this->depot->disque()->exists($fichier->chemin), 404);

        // Jamais servi inline ni avec son type d'origine : un fichier d'enfant ne s'exécute pas dans le navigateur.
        return $this->depot->disque()->download($fichier->chemin, $fichier->nom_original, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function autoriser(Classe $classe, ?DepotFichier $fichier = null): void
    {
        abort_unless($classe->isStage(), 404);
        Gate::authorize('deposer', $classe);
        abort_if($fichier !== null && $fichier->espace->classe_id !== $classe->id, 404);
    }
}
