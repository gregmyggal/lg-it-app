<?php

namespace App\Http\Controllers;

use App\Services\TimesheetSyntheseMoisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-01 T2 : synthèse mensuelle par professeur. Staff uniquement. */
class TimesheetSyntheseMoisController extends Controller
{
    public function show(Request $request, TimesheetSyntheseMoisService $service): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
        ]);

        return response()->json($service->synthese((int) $v['annee'], (int) $v['mois'], $request->user()));
    }
}
