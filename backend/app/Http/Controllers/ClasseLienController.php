<?php

namespace App\Http\Controllers;

use App\Models\Anniversaire;
use App\Models\ClasseLien;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClasseLienController extends Controller
{
    // Whitelist explicite : jamais d'instanciation de classe à partir de l'input brut.
    private const PARENT_TYPES = [
        'cours' => Cours::class,
        'stages' => Stage::class,
        'formations' => Formation::class,
        'anniversaires' => Anniversaire::class,
    ];

    public function index(string $parentType, int $parentId)
    {
        $parent = $this->resolveParent($parentType, $parentId);

        Gate::authorize('viewAny', [ClasseLien::class, $parent]);

        return $parent->liensClasse;
    }

    public function store(Request $request, string $parentType, int $parentId)
    {
        $parent = $this->resolveParent($parentType, $parentId);

        Gate::authorize('create', [ClasseLien::class, $parent]);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'description' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
            'seance' => ['nullable', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer'],
            'actif' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        return response()->json($parent->liensClasse()->create($data), 201);
    }

    public function update(Request $request, ClasseLien $lien)
    {
        Gate::authorize('update', $lien);

        $data = $request->validate([
            'titre' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url'],
            'description' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
            'seance' => ['nullable', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer'],
            'actif' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        $lien->update($data);

        return $lien;
    }

    public function destroy(ClasseLien $lien)
    {
        Gate::authorize('delete', $lien);

        $lien->delete();

        return response()->noContent();
    }

    private function resolveParent(string $parentType, int $parentId): Model
    {
        abort_unless(array_key_exists($parentType, self::PARENT_TYPES), 404);

        return self::PARENT_TYPES[$parentType]::findOrFail($parentId);
    }
}
