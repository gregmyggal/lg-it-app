<?php

namespace App\Http\Controllers;

use App\Http\Requests\DefinirEmployeurMoisRequest;
use App\Http\Requests\LotEmployeurMoisRequest;
use App\Http\Resources\EmployeurMoisAuditResource;
use App\Models\Employeur;
use App\Models\Professeur;
use App\Models\ProfesseurEmployeurMois;
use App\Services\EmployeurMoisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** EMP-01 : employeur d'un animateur par mois — frise, édition, lot, historique. */
class EmployeurMoisController extends Controller
{
    public function __construct(private readonly EmployeurMoisService $service) {}

    // Staff : 12 mois complets. Professeur (le sien) : nom de l'entité seulement, sans IBAN, verrou ni version (RG-10).
    public function index(Request $request, Professeur $professeur): JsonResponse
    {
        Gate::authorize('view', [ProfesseurEmployeurMois::class, $professeur]);
        $annee = (int) $request->validate(['annee' => ['required', 'integer', 'min:2020', 'max:2100']])['annee'];

        $mois = $this->service->mois($professeur, $annee, $request->user());
        if (! $request->user()->isStaff()) {
            $mois = array_map(fn (array $m) => ['mois' => $m['mois'], 'employeur' => ['nom' => $m['employeur']['nom']]], $mois);
        }

        return response()->json(['annee' => $annee, 'mois' => $mois]);
    }

    public function update(DefinirEmployeurMoisRequest $request, Professeur $professeur, int $annee, int $mois): JsonResponse
    {
        abort_unless($mois >= 1 && $mois <= 12, 404);
        $v = $request->validated();

        $this->service->definir($professeur, $annee, $mois, Employeur::findOrFail($v['employeur_id']), $v['motif'] ?? null, (int) $v['version'], $request->user());

        return response()->json(['data' => $this->service->pourProfesseur($professeur, $annee, $mois, $request->user())]);
    }

    public function lot(LotEmployeurMoisRequest $request): JsonResponse
    {
        $v = $request->validated();
        $reprendre = (bool) ($v['reprendre_precedent'] ?? false);

        return response()->json($this->service->lot(
            $v['professeur_ids'], (int) $v['annee'], (int) $v['mois'],
            $reprendre ? null : Employeur::findOrFail($v['employeur_id']), $reprendre, $v['motif'] ?? null, $request->user(),
        ));
    }

    public function historique(Request $request, Professeur $professeur): JsonResponse
    {
        Gate::authorize('viewHistorique', [ProfesseurEmployeurMois::class, $professeur]);
        $annee = $request->validate(['annee' => ['sometimes', 'integer', 'min:2020', 'max:2100']])['annee'] ?? null;

        return EmployeurMoisAuditResource::collection($this->service->historique($professeur, $annee))->response();
    }
}
