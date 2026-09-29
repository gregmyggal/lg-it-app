<?php

namespace App\Http\Controllers;

use App\Models\CourseSession;
use App\Models\Cours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseSessionController extends Controller
{
    public function indexByCourse(Cours $cours): JsonResponse
    {
        $sessions = $cours->sessions()
            ->with(['professeurs', 'sessionProfessors'])
            ->orderBy('date_debut')
            ->paginate(20);

        return response()->json([
            'data' => $sessions->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = CourseSession::query()
            ->with(['cours', 'professeurs', 'sessionProfessors']);

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('date_debut', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date_debut', '<=', $request->to_date);
        }

        // Filter by status
        if ($request->filled('statut')) {
            $statuts = is_array($request->statut)
                ? $request->statut
                : explode(',', $request->statut);
            $query->whereIn('statut', $statuts);
        }

        // Filter by professor
        if ($request->filled('professeur_id')) {
            $query->whereHas('professeurs', function ($q) {
                $q->where('professeur_id', request('professeur_id'));
            });
        }

        // Filter by course
        if ($request->filled('cours_id')) {
            $query->where('cours_id', $request->cours_id);
        }

        $sessions = $query->orderBy('date_debut', 'desc')->paginate(20);

        return response()->json([
            'data' => $sessions->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cours_id' => 'required|exists:cours,id',
            'recurrence_id' => 'nullable|exists:course_recurrences,id',
            'date_debut' => 'required|date',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'titre' => 'nullable|string',
            'lieu' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'professor_principal_id' => 'nullable|exists:professeurs,id',
            'nb_eleves_attendus' => 'sometimes|integer|min:0',
        ]);

        $session = CourseSession::create($validated);

        return response()->json([
            'data' => $session->load(['cours', 'professeurs']),
            'message' => 'Session créée avec succès',
        ], 201);
    }

    public function show(CourseSession $session): JsonResponse
    {
        return response()->json([
            'data' => $session->load(['cours', 'professeurs', 'sessionProfessors']),
        ]);
    }

    public function update(CourseSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_debut' => 'sometimes|date',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin' => 'sometimes|date_format:H:i',
            'titre' => 'nullable|string',
            'lieu' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'statut' => ['sometimes', Rule::in('scheduled', 'in_progress', 'completed', 'cancelled')],
            'professor_principal_id' => 'nullable|exists:professeurs,id',
            'nb_eleves_attendus' => 'sometimes|integer|min:0',
            'nb_eleves_presentes' => 'nullable|integer|min:0',
        ]);

        $session->update($validated);

        return response()->json([
            'data' => $session->load(['cours', 'professeurs']),
            'message' => 'Session mise à jour avec succès',
        ]);
    }

    public function destroy(CourseSession $session): JsonResponse
    {
        $session->delete();

        return response()->json([
            'message' => 'Session supprimée avec succès',
        ]);
    }

    public function cancel(CourseSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'motif' => 'nullable|string',
        ]);

        $session->cancel($validated['motif'] ?? null);

        return response()->json([
            'data' => $session,
            'message' => 'Session annulée avec succès',
        ]);
    }

    public function markInProgress(CourseSession $session): JsonResponse
    {
        if (!$session->isScheduled()) {
            return response()->json([
                'error' => 'Seules les sessions planifiées peuvent être marquées comme en cours',
            ], 422);
        }

        $session->update(['statut' => 'in_progress']);

        return response()->json([
            'data' => $session,
            'message' => 'Session marquée comme en cours',
        ]);
    }

    public function markCompleted(CourseSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nb_eleves_presentes' => 'sometimes|integer|min:0',
        ]);

        $session->update(array_merge($validated, ['statut' => 'completed']));

        return response()->json([
            'data' => $session,
            'message' => 'Session marquée comme complétée',
        ]);
    }
}
