<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Services\TimesheetNotifier;
use App\Services\TimesheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-05 : l'admin et la direction soumettent le mois d'un professeur en l'état (message obligatoire). */
class TimesheetSoumissionForceeController extends Controller
{
    public function store(Request $request, Professeur $professeur, TimesheetService $service, TimesheetNotifier $notifier): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'motif' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $resume = $service->forcerSoumission($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), trim($v['motif']));
        $notifier->soumissionForcee($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), $resume, trim($v['motif']));

        return response()->json($resume);
    }
}
