<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursController extends Controller
{
    // Catalogue public (non authentifié) — uniquement les cours publiés, sans données privées.
    public function publicIndex()
    {
        return Cours::with('typesCours')
            ->where('statut', 'publish')
            ->orderBy('menu_order')
            ->get();
    }

    public function publicShow(Cours $cours)
    {
        abort_unless($cours->statut === 'publish', 404);

        return $cours->load(['typesCours', 'ressources']);
    }

    // Isolation par professeur (US-401/406) : chaque profil ne voit que les cours dont
    // un type_cours correspond à ses types assignés ; le staff voit tout, tout statut.
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Cours::class);

        $user = $request->user();

        $query = Cours::with('typesCours', 'ressources');

        if (! $user->isStaff()) {
            $typeIds = $user->professeur?->typesCours()->pluck('types_cours.id') ?? collect();

            $query->whereHas('typesCours', fn ($q) => $q->whereIn('types_cours.id', $typeIds))
                ->where('statut', 'publish');
        }

        return $query->orderBy('menu_order')->get();
    }

    public function show(Request $request, Cours $cours)
    {
        Gate::authorize('view', $cours);

        return $cours->load(['typesCours', 'ressources']);
    }

    // Réservé staff (CoursPolicy) : un professeur ne modifie jamais la structure d'un cours.
    public function store(Request $request)
    {
        Gate::authorize('create', Cours::class);

        $data = $this->validated($request);

        $cours = Cours::create($data);
        $cours->typesCours()->sync($data['types_cours'] ?? []);

        return response()->json($cours->load('typesCours'), 201);
    }

    public function update(Request $request, Cours $cours)
    {
        Gate::authorize('update', $cours);

        $data = $this->validated($request, $cours);

        $cours->update($data);

        if (array_key_exists('types_cours', $data)) {
            $cours->typesCours()->sync($data['types_cours']);
        }

        return $cours->load('typesCours');
    }

    public function destroy(Cours $cours)
    {
        Gate::authorize('delete', $cours);

        $cours->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Cours $cours = null): array
    {
        $uniqueSlug = 'unique:cours,slug'.($cours ? ','.$cours->id : '');

        return $request->validate([
            'titre' => [$cours ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [$cours ? 'sometimes' : 'required', 'string', 'max:255', $uniqueSlug],
            'contenu' => ['nullable', 'string'],
            'extrait' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string'],
            'url_logiscool' => ['nullable', 'url'],
            'menu_order' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:publish,draft'],
            'types_cours' => ['nullable', 'array'],
            'types_cours.*' => ['integer', 'exists:types_cours,id'],
        ]);
    }
}
