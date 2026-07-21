<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\CoursRessource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CoursRessourceController extends Controller
{
    public function index(Cours $cours)
    {
        Gate::authorize('view', $cours);

        return $cours->ressources;
    }

    public function store(Request $request, Cours $cours)
    {
        Gate::authorize('create', [CoursRessource::class, $cours]);

        $data = $request->validate([
            'titre_ressource' => ['required', 'string', 'max:255'],
            'url_ressource' => ['required', 'url'],
            'type_ressource' => ['required', 'in:video,outil,document,jeu'],
            'ordre' => ['nullable', 'integer'],
        ]);

        return response()->json($cours->ressources()->create($data), 201);
    }

    public function update(Request $request, CoursRessource $coursRessource)
    {
        Gate::authorize('update', $coursRessource);

        $data = $request->validate([
            'titre_ressource' => ['sometimes', 'string', 'max:255'],
            'url_ressource' => ['sometimes', 'url'],
            'type_ressource' => ['sometimes', 'in:video,outil,document,jeu'],
            'ordre' => ['nullable', 'integer'],
        ]);

        $coursRessource->update($data);

        return $coursRessource;
    }

    public function destroy(CoursRessource $coursRessource)
    {
        Gate::authorize('delete', $coursRessource);

        $coursRessource->delete();

        return response()->noContent();
    }
}
