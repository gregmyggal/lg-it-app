<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Rules\PasDeQuinzeMinutes;
use App\Services\HeuresDefrayablesImpactService;
use App\Services\TimesheetParametreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** DEF-01 T3 : durée de séance et heures défrayables applicables à un cours pour une année (création de classe). Staff uniquement. */
class HeuresDefrayablesController extends Controller
{
    public function show(Request $request, TimesheetParametreService $parametres): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'cours_id' => ['nullable', 'integer', 'exists:cours,id'],
        ]);
        $cours = isset($v['cours_id']) ? Cours::find($v['cours_id']) : null;

        return response()->json(['data' => [
            'duree_seance_defaut' => $parametres->dureeSeanceDefaut((int) $v['annee']),
        ] + $parametres->heuresDefrayablesPour($cours, (int) $v['annee'])]);
    }

    // Simulation avant enregistrement : combien de jours dépasseraient le plafond journalier avec cette valeur ? (information, jamais bloquant)
    public function impact(Request $request, HeuresDefrayablesImpactService $impact): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'portee' => ['required', 'in:global,cours'],
            'cours_id' => ['required_if:portee,cours', 'nullable', 'integer', 'exists:cours,id'],
            'heures' => ['required', 'numeric', 'between:0.5,8', new PasDeQuinzeMinutes],
        ]);

        return response()->json(['data' => $impact->simuler((int) $v['annee'], $v['portee'], isset($v['cours_id']) ? (int) $v['cours_id'] : null, (float) $v['heures'])]);
    }
}
