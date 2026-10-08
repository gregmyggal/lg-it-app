<?php

namespace App\Http\Controllers;

use App\Exceptions\RegleMetierException;
use App\Http\Requests\StoreEmployeurRequest;
use App\Http\Requests\UpdateEmployeurRequest;
use App\Http\Resources\EmployeurResource;
use App\Models\Employeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** EMP-01 : entités employeurs. Lecture staff, gestion admin ; une entité utilisée se désactive, elle ne se supprime pas. */
class EmployeurController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Employeur::class);

        return EmployeurResource::collection(Employeur::withCount('moisLies')->orderByDesc('par_defaut')->orderBy('nom')->get())->response();
    }

    public function store(StoreEmployeurRequest $request): JsonResponse
    {
        $employeur = DB::transaction(function () use ($request) {
            $v = $request->validated();
            if ($v['par_defaut'] ?? false) {
                Employeur::where('par_defaut', true)->update(['par_defaut' => false]);
            }

            return Employeur::create($v + ['actif' => true]);
        });

        return (new EmployeurResource($employeur->loadCount('moisLies')))->response()->setStatusCode(201);
    }

    public function update(UpdateEmployeurRequest $request, Employeur $employeur): EmployeurResource
    {
        $v = $request->validated();

        DB::transaction(function () use ($employeur, $v) {
            Employeur::lockForUpdate()->get(); // sérialise les changements d'entité par défaut
            $employeur->refresh();

            if (array_key_exists('par_defaut', $v) && ! $v['par_defaut'] && $employeur->par_defaut) {
                throw RegleMetierException::invalide('Il doit toujours y avoir une entité par défaut : désignez-en une autre pour remplacer celle-ci.', ['par_defaut' => ['Une entité doit rester par défaut.']]);
            }
            if (array_key_exists('actif', $v) && ! $v['actif'] && ($employeur->par_defaut || ($v['par_defaut'] ?? false))) {
                throw RegleMetierException::invalide('L\'entité par défaut ne peut pas être désactivée.', ['actif' => ['Entité par défaut.']]);
            }
            if (($v['par_defaut'] ?? false) && ! ($v['actif'] ?? $employeur->actif)) {
                throw RegleMetierException::invalide('Une entité désactivée ne peut pas être l\'entité par défaut.', ['par_defaut' => ['Entité désactivée.']]);
            }
            if ($v['par_defaut'] ?? false) {
                Employeur::where('par_defaut', true)->where('id', '!=', $employeur->id)->update(['par_defaut' => false]);
            }
            $employeur->update($v);
        });

        return new EmployeurResource($employeur->refresh()->loadCount('moisLies'));
    }
}
