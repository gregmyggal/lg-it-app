<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StageController extends Controller
{
    public function publicIndex()
    {
        return Stage::with('dates')->where('statut', 'publish')->orderBy('menu_order')->get();
    }

    public function publicShow(Stage $stage)
    {
        abort_unless($stage->statut === 'publish', 404);

        return $stage->load('dates');
    }

    public function index()
    {
        Gate::authorize('viewAny', Stage::class);

        return Stage::with('dates')->orderBy('menu_order')->get();
    }

    public function show(Stage $stage)
    {
        Gate::authorize('view', $stage);

        return $stage->load('dates');
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Stage::class);

        $data = $this->validated($request);

        return response()->json(Stage::create($data), 201);
    }

    public function update(Request $request, Stage $stage)
    {
        Gate::authorize('update', $stage);

        $stage->update($this->validated($request, $stage));

        return $stage->load('dates');
    }

    public function destroy(Stage $stage)
    {
        Gate::authorize('delete', $stage);

        $stage->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Stage $stage = null): array
    {
        $uniqueSlug = 'unique:stages,slug'.($stage ? ','.$stage->id : '');
        $required = $stage ? 'sometimes' : 'required';

        return $request->validate([
            'titre' => [$required, 'string', 'max:255'],
            'slug' => [$required, 'string', 'max:255', $uniqueSlug],
            'theme_stage' => [$required, 'string', 'max:255'],
            'tranche_age' => [$required, 'string', 'max:255'],
            'lieu' => [$required, 'string', 'max:255'],
            'prix' => [$required, 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string'],
            'url_logiscool' => ['nullable', 'url'],
            'sessions_vacances' => ['nullable', 'array'],
            'sessions_vacances.*' => ['in:Été,Toussaint,Noël,Carnaval,Pâques'],
            'menu_order' => ['nullable', 'integer'],
            'statut' => ['nullable', 'in:publish,draft'],
        ]);
    }
}
