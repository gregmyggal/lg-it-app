<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\TimesheetNote;
use App\Services\TimesheetNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-06 : notes internes, réservées à la direction et à l'admin (403 pour le professeur). */
class TimesheetNoteController extends Controller
{
    private const PERIODE = [
        'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
        'mois' => ['required', 'integer', 'between:1,12'],
        'timesheet_id' => ['nullable', 'integer'],
    ];

    public function enregistrer(Request $request, Professeur $professeur, TimesheetNoteService $notes): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate(self::PERIODE + ['texte' => ['required', 'string', 'min:1', 'max:'.TimesheetNote::TAILLE_MAX]]);

        $note = $notes->enregistrer($professeur, (int) $v['annee'], (int) $v['mois'], $v['timesheet_id'] ?? null, $v['texte'], $request->user());

        return response()->json(['id' => $note->id, 'updated_at' => $note->updated_at] + $notes->pourMois($professeur, (int) $v['annee'], (int) $v['mois'], $request->user()));
    }

    public function supprimer(Request $request, Professeur $professeur, TimesheetNoteService $notes): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate(self::PERIODE);

        $notes->supprimer($professeur, (int) $v['annee'], (int) $v['mois'], isset($v['timesheet_id']) ? (int) $v['timesheet_id'] : null, $request->user());

        return response()->json($notes->pourMois($professeur, (int) $v['annee'], (int) $v['mois'], $request->user()));
    }

    /** Quels professeurs ont déjà une note interne ce mois-ci (modale de validation). */
    public function presence(Request $request, TimesheetNoteService $notes): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'professeur_ids' => ['required', 'array', 'min:1', 'max:200'],
            'professeur_ids.*' => ['integer', 'distinct'],
        ]);

        return response()->json(['data' => $notes->presence($v['professeur_ids'], (int) $v['annee'], (int) $v['mois'], $request->user())]);
    }

    public function historique(Request $request, Professeur $professeur, TimesheetNote $note, TimesheetNoteService $notes): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        return response()->json(['data' => $notes->historique($professeur, $note)]);
    }
}
