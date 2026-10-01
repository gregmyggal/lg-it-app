<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursController extends Controller
{
    // Isolation par professeur (CLS-01 T2) : un professeur ne voit que les cours publiés dont il a une classe
    // active (assignation professeur_classe) ; le staff voit tout, tout statut.
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Cours::class);

        $user = $request->user();

        // T4 : nombre de liens (généraux / de séance) pour les cartes « Liens de mes cours » et la liste admin.
        $query = Cours::with('ressources')->withCount([
            'liensClasse as liens_generaux_count' => fn ($q) => $q->whereNull('seance_numero'),
            'liensClasse as liens_seance_count' => fn ($q) => $q->whereNotNull('seance_numero')->where('seance_numero', '<=', 14),
        ]);

        if (! $user->isStaff()) {
            $query->where('statut', 'publish');

            if ($user->professeur) {
                $query->whereIn('cours.id', $user->professeur->cours()->select('cours.id'));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderBy('menu_order')->get();
    }

    public function show(Request $request, Cours $cours)
    {
        Gate::authorize('view', $cours);

        return $cours->load('ressources');
    }

    // Réservé staff (CoursPolicy) : un professeur ne modifie jamais la structure d'un cours.
    public function store(Request $request)
    {
        Gate::authorize('create', Cours::class);

        $data = $this->validated($request);

        $cours = Cours::create($data);

        return response()->json($cours, 201);
    }

    public function update(Request $request, Cours $cours)
    {
        Gate::authorize('update', $cours);

        $data = $this->validated($request, $cours);

        $cours->update($data);

        return $cours;
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
        ]);
    }
}
