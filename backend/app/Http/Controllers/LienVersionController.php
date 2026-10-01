<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClasseLienResource;
use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use App\Models\Cours;
use App\Services\CoursLienService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Restauration d'une version de lien (CLS-01 T4) : annule une modification, une suppression ou un ordre. */
class LienVersionController extends Controller
{
    public function __construct(private readonly CoursLienService $service) {}

    public function restaurer(Request $request, ClasseLienVersion $version): JsonResponse
    {
        $cours = Cours::findOrFail($version->parent_id);
        Gate::authorize('create', [ClasseLien::class, $cours]); // même droit que l'écriture (RG-6)

        $v = $request->validate(['confirmer' => ['sometimes', 'boolean']]);
        $resultat = $this->service->restaurer($version, $request->user(), (bool) ($v['confirmer'] ?? false));

        return response()->json([
            'data' => $resultat instanceof Collection
                ? ClasseLienResource::collection($resultat)->resolve($request)
                : (new ClasseLienResource($resultat))->resolve($request),
        ]);
    }
}
