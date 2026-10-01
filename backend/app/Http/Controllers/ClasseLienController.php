<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCoursLienRequest;
use App\Http\Resources\ClasseLienResource;
use App\Models\Anniversaire;
use App\Models\ClasseLien;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\Stage;
use App\Services\CoursLienService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClasseLienController extends Controller
{
    public function __construct(private readonly CoursLienService $liens) {}

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

        // CLS-01 T4 : les liens d'un COURS sont versionnés (concurrence optimiste, historique) ; les autres parents
        // (stages, formations, anniversaires) gardent le comportement historique.
        if ($lien->parent_type === ClasseLien::PARENT_COURS) {
            $data = app(UpdateCoursLienRequest::class)->validated();
            $lien = $this->liens->modifier($lien, $request->user(), collect($data)->except('version')->all(), $data['version']);

            return new ClasseLienResource($lien);
        }

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

        // CLS-01 T4 : supprimer un lien de cours = l'archiver (restaurable 6 mois) ; les autres parents : suppression physique.
        if ($lien->parent_type === ClasseLien::PARENT_COURS) {
            $version = $this->liens->archiver($lien, request()->user());

            return response()->json(['historique_id' => $version->id]);
        }

        $lien->forceDelete();

        return response()->noContent();
    }

    private function resolveParent(string $parentType, int $parentId): Model
    {
        abort_unless(array_key_exists($parentType, self::PARENT_TYPES), 404);

        return self::PARENT_TYPES[$parentType]::findOrFail($parentId);
    }
}
