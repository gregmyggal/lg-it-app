<?php

namespace App\Http\Controllers;

use App\Models\CourseSession;
use App\Models\SessionProfessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionProfessorController extends Controller
{
    public function indexBySession(CourseSession $session): JsonResponse
    {
        $professors = $session->sessionProfessors()
            ->with('professeur')
            ->orderByRaw("FIELD(role, 'principal', 'assistant', 'substitute', 'observer')")
            ->get();

        return response()->json([
            'data' => $professors,
            'count' => $professors->count(),
        ]);
    }

    public function store(CourseSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'professeur_id' => 'required|exists:professeurs,id',
            'role' => ['required', Rule::in('principal', 'assistant', 'substitute', 'observer')],
        ]);

        // Check if already assigned with this role
        $existing = SessionProfessor::where('course_session_id', $session->id)
            ->where('professeur_id', $validated['professeur_id'])
            ->where('role', $validated['role'])
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Ce professeur est déjà assigné avec ce rôle',
            ], 422);
        }

        // If principal, remove other principals (only one per session)
        if ($validated['role'] === 'principal') {
            SessionProfessor::where('course_session_id', $session->id)
                ->where('role', 'principal')
                ->delete();
        }

        $assignment = SessionProfessor::create([
            'course_session_id' => $session->id,
            'professeur_id' => $validated['professeur_id'],
            'role' => $validated['role'],
        ]);

        return response()->json([
            'data' => $assignment->load('professeur'),
            'message' => 'Professeur assigné avec succès',
        ], 201);
    }

    public function update(CourseSession $session, SessionProfessor $assignment, Request $request): JsonResponse
    {
        if ($assignment->course_session_id !== $session->id) {
            return response()->json([
                'error' => 'Cette assignation n\'appartient pas à cette session',
            ], 404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', Rule::in('principal', 'assistant', 'substitute', 'observer')],
            'present' => 'sometimes|boolean',
            'motif_absence' => 'nullable|string',
        ]);

        // If changing to principal, remove other principals
        if (isset($validated['role']) && $validated['role'] === 'principal') {
            SessionProfessor::where('course_session_id', $session->id)
                ->where('id', '!=', $assignment->id)
                ->where('role', 'principal')
                ->delete();
        }

        $assignment->update($validated);

        return response()->json([
            'data' => $assignment->load('professeur'),
            'message' => 'Assignation mise à jour avec succès',
        ]);
    }

    public function destroy(CourseSession $session, SessionProfessor $assignment): JsonResponse
    {
        if ($assignment->course_session_id !== $session->id) {
            return response()->json([
                'error' => 'Cette assignation n\'appartient pas à cette session',
            ], 404);
        }

        $assignment->delete();

        return response()->json([
            'message' => 'Professeur retiré de la session',
        ]);
    }

    public function bulk(CourseSession $session, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'professors' => 'required|array',
            'professors.*.professeur_id' => 'required|exists:professeurs,id',
            'professors.*.role' => ['required', Rule::in('principal', 'assistant', 'substitute', 'observer')],
        ]);

        // Delete all existing assignments for this session
        $session->sessionProfessors()->delete();

        $assignments = [];
        foreach ($validated['professors'] as $prof) {
            $assignment = SessionProfessor::create([
                'course_session_id' => $session->id,
                'professeur_id' => $prof['professeur_id'],
                'role' => $prof['role'],
            ]);
            $assignments[] = $assignment;
        }

        return response()->json([
            'data' => $assignments,
            'message' => count($assignments) . ' professeurs assignés',
        ]);
    }

    public function markPresent(CourseSession $session, SessionProfessor $assignment, Request $request): JsonResponse
    {
        if ($assignment->course_session_id !== $session->id) {
            return response()->json([
                'error' => 'Cette assignation n\'appartient pas à cette session',
            ], 404);
        }

        $assignment->update(['present' => true]);

        return response()->json([
            'data' => $assignment,
            'message' => 'Professeur marqué comme présent',
        ]);
    }

    public function markAbsent(CourseSession $session, SessionProfessor $assignment, Request $request): JsonResponse
    {
        if ($assignment->course_session_id !== $session->id) {
            return response()->json([
                'error' => 'Cette assignation n\'appartient pas à cette session',
            ], 404);
        }

        $validated = $request->validate([
            'motif' => 'nullable|string',
        ]);

        $assignment->update([
            'present' => false,
            'motif_absence' => $validated['motif'] ?? null,
        ]);

        return response()->json([
            'data' => $assignment,
            'message' => 'Professeur marqué comme absent',
        ]);
    }
}
