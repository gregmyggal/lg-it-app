<?php

namespace App\Http\Controllers;

use App\Models\Anniversaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnniversaireController extends Controller
{
    public function publicIndex()
    {
        return Anniversaire::where('statut', 'publish')->orderBy('menu_order')->get();
    }

    public function publicShow(Anniversaire $anniversaire)
    {
        abort_unless($anniversaire->statut === 'publish', 404);

        return $anniversaire;
    }

    public function index()
    {
        Gate::authorize('viewAny', Anniversaire::class);

        return Anniversaire::orderBy('menu_order')->get();
    }

    public function show(Anniversaire $anniversaire)
    {
        Gate::authorize('view', $anniversaire);

        return $anniversaire;
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Anniversaire::class);

        return response()->json(Anniversaire::create($this->validated($request)), 201);
    }

    public function update(Request $request, Anniversaire $anniversaire)
    {
        Gate::authorize('update', $anniversaire);

        $anniversaire->update($this->validated($request, $anniversaire));

        return $anniversaire;
    }

    public function destroy(Anniversaire $anniversaire)
    {
        Gate::authorize('delete', $anniversaire);

        $anniversaire->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Anniversaire $anniversaire = null): array
    {
        $uniqueSlug = 'unique:anniversaires,slug'.($anniversaire ? ','.$anniversaire->id : '');
        $required = $anniversaire ? 'sometimes' : 'required';

        return $request->validate([
            'nom_theme' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', $uniqueSlug],
            'description' => [$required, 'string'],
            'tranche_age' => [$required, 'string', 'max:255'],
            'tarif' => [$required, 'numeric', 'min:0'],
            'image_path' => ['nullable', 'string'],
            'url_logiscool' => ['nullable', 'url'],
            'inclus' => ['nullable', 'string'],
            'options' => ['nullable', 'string'],
            'menu_order' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:publish,draft'],
        ]);
    }
}
