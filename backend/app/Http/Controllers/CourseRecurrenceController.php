<?php

namespace App\Http\Controllers;

use App\Models\CourseRecurrence;
use App\Models\Cours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseRecurrenceController extends Controller
{
    public function index(Cours $cours): JsonResponse
    {
        $recurrences = $cours->recurrences()
            ->orderBy('date_debut')
            ->get();

        return response()->json([
            'data' => $recurrences,
            'count' => $recurrences->count(),
        ]);
    }

    public function store(Request $cours, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in('weekly', 'biweekly', 'monthly')],
            'jours_semaine' => 'nullable|string|max:50',
            'date_debut' => 'required|date',
            'date_fin' => 'nullable|date|after:date_debut',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'lieu_defaut' => 'nullable|string|max:255',
        ]);

        $validated['cours_id'] = $cours->id;

        $recurrence = CourseRecurrence::create($validated);

        return response()->json([
            'data' => $recurrence,
            'message' => 'Récurrence créée avec succès',
        ], 201);
    }

    public function show(Cours $cours, CourseRecurrence $recurrence): JsonResponse
    {
        if ($recurrence->cours_id !== $cours->id) {
            return response()->json([
                'error' => 'Cette récurrence n\'appartient pas à ce cours',
            ], 404);
        }

        return response()->json([
            'data' => $recurrence->load('sessions'),
        ]);
    }

    public function update(Cours $cours, CourseRecurrence $recurrence, Request $request): JsonResponse
    {
        if ($recurrence->cours_id !== $cours->id) {
            return response()->json([
                'error' => 'Cette récurrence n\'appartient pas à ce cours',
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['sometimes', Rule::in('weekly', 'biweekly', 'monthly')],
            'jours_semaine' => 'nullable|string|max:50',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'nullable|date|after:date_debut',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin' => 'sometimes|date_format:H:i',
            'lieu_defaut' => 'nullable|string|max:255',
            'statut' => ['sometimes', Rule::in('active', 'paused', 'archived')],
        ]);

        $recurrence->update($validated);

        return response()->json([
            'data' => $recurrence,
            'message' => 'Récurrence mise à jour avec succès',
        ]);
    }

    public function destroy(Cours $cours, CourseRecurrence $recurrence): JsonResponse
    {
        if ($recurrence->cours_id !== $cours->id) {
            return response()->json([
                'error' => 'Cette récurrence n\'appartient pas à ce cours',
            ], 404);
        }

        $recurrence->delete();

        return response()->json([
            'message' => 'Récurrence supprimée avec succès',
        ]);
    }

    /**
     * Generate sessions from this recurrence rule.
     */
    public function generateSessions(Cours $cours, CourseRecurrence $recurrence, Request $request): JsonResponse
    {
        if ($recurrence->cours_id !== $cours->id) {
            return response()->json([
                'error' => 'Cette récurrence n\'appartient pas à ce cours',
            ], 404);
        }

        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'override_existing' => 'sometimes|boolean',
        ]);

        // TODO: Implement session generation logic
        // For now, return a placeholder response

        return response()->json([
            'message' => 'Sessions en cours de génération',
            'count' => 0,
        ]);
    }
}
