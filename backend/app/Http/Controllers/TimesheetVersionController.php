<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Services\TimesheetVersionVueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-04 : versions « soumise » et « validée » d'un mois, en lecture seule. */
class TimesheetVersionController extends Controller
{
    private const REGLES = [
        'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
        'mois' => ['required', 'integer', 'between:1,12'],
        'soumise_numero' => ['sometimes', 'integer', 'min:1'],
        'validee_numero' => ['sometimes', 'integer', 'min:1'],
    ];

    /** Direction et admin : n'importe quel professeur, avec empreinte et auteur. */
    public function show(Request $request, Professeur $professeur, TimesheetVersionVueService $vues): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate(self::REGLES);

        return response()->json($vues->vue($professeur, (int) $v['annee'], (int) $v['mois'], true, $v['soumise_numero'] ?? null, $v['validee_numero'] ?? null));
    }

    /** Professeur : uniquement ses propres versions, sans empreinte ni auteur. */
    public function moi(Request $request, TimesheetVersionVueService $vues): JsonResponse
    {
        $professeur = $request->user()->professeur;
        abort_unless($professeur, 403, 'Action non autorisée.');
        $v = $request->validate(self::REGLES);

        return response()->json($vues->vue($professeur, (int) $v['annee'], (int) $v['mois'], false, $v['soumise_numero'] ?? null, $v['validee_numero'] ?? null));
    }
}
