<?php

namespace App\Http\Controllers;

use App\Models\Formation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormationController extends Controller
{
    public function publicIndex()
    {
        return Formation::with('typesFormation')->where('statut', 'publish')->orderBy('menu_order')->get();
    }

    public function publicShow(Formation $formation)
    {
        abort_unless($formation->statut === 'publish', 404);

        return $formation->load('typesFormation');
    }

    public function index()
    {
        Gate::authorize('viewAny', Formation::class);

        return Formation::with('typesFormation')->orderBy('menu_order')->get();
    }

    public function show(Formation $formation)
    {
        Gate::authorize('view', $formation);

        return $formation->load('typesFormation');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Formation::class);

        $data = $this->validated($request);

        $formation = Formation::create($data);
        $formation->typesFormation()->sync($data['types_formation'] ?? []);

        return response()->json($formation->load('typesFormation'), 201);
    }

    public function update(Request $request, Formation $formation)
    {
        Gate::authorize('update', $formation);

        $data = $this->validated($request, $formation);

        $formation->update($data);

        if (array_key_exists('types_formation', $data)) {
            $formation->typesFormation()->sync($data['types_formation']);
        }

        return $formation->load('typesFormation');
    }

    public function destroy(Formation $formation)
    {
        Gate::authorize('delete', $formation);

        $formation->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Formation $formation = null): array
    {
        $uniqueSlug = 'unique:formations,slug'.($formation ? ','.$formation->id : '');
        $required = $formation ? 'sometimes' : 'required';

        return $request->validate([
            'titre' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', $uniqueSlug],
            'programme' => ['nullable', 'string'],
            'extrait' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string'],
            'format' => ['nullable', 'in:presentiel,en_ligne,hybride'],
            'duree' => ['nullable', 'string', 'max:255'],
            'prix' => ['nullable', 'numeric', 'min:0'],
            'public_cible' => ['nullable', 'string', 'max:255'],
            'niveau' => ['nullable', 'in:debutant,initie,confirme,tous'],
            'prochaine_date' => ['nullable', 'date'],
            'objectifs' => ['nullable', 'string'],
            'url_inscription' => ['nullable', 'url'],
            'menu_order' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:publish,draft'],
            'types_formation' => ['nullable', 'array'],
            'types_formation.*' => ['integer', 'exists:types_formation,id'],
        ]);
    }
}
