<?php

namespace App\Http\Controllers;

use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\SessionCalendarView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * Get sessions for a specific month.
     */
    public function month(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'professeur_id' => 'nullable|exists:professeurs,id',
        ]);

        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfDay();
        $endDate = $startDate->clone()->endOfMonth();

        $query = CourseSession::query()
            ->with(['cours', 'professeurs', 'sessionProfessors'])
            ->whereBetween('date_debut', [$startDate, $endDate]);

        // Filter by professor if requested
        if ($request->filled('professeur_id')) {
            $query->whereHas('professeurs', function ($q) {
                $q->where('professeur_id', request('professeur_id'));
            });
        }

        $sessions = $query->orderBy('date_debut')->get();

        // Group by date for calendar view
        $calendar = $sessions->groupBy(function ($session) {
            return $session->date_debut->format('Y-m-d');
        });

        return response()->json([
            'year' => $validated['year'],
            'month' => $validated['month'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $calendar,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
            ],
        ]);
    }

    /**
     * Get sessions for a specific week.
     */
    public function week(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'week' => 'required|integer|min:1|max:53',
            'professeur_id' => 'nullable|exists:professeurs,id',
        ]);

        $date = Carbon::now()
            ->setISODate($validated['year'], $validated['week'], 1)
            ->startOfDay();

        $startDate = $date->clone();
        $endDate = $date->clone()->addDays(6)->endOfDay();

        $query = CourseSession::query()
            ->with(['cours', 'professeurs', 'sessionProfessors'])
            ->whereBetween('date_debut', [$startDate, $endDate]);

        if ($request->filled('professeur_id')) {
            $query->whereHas('professeurs', function ($q) {
                $q->where('professeur_id', request('professeur_id'));
            });
        }

        $sessions = $query->orderBy('date_debut')->get();

        return response()->json([
            'year' => $validated['year'],
            'week' => $validated['week'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $sessions,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_day' => $sessions->groupBy(fn($s) => $s->date_debut->dayName)->map->count(),
            ],
        ]);
    }

    /**
     * Get sessions for a full year.
     */
    public function year(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'professeur_id' => 'nullable|exists:professeurs,id',
        ]);

        $startDate = Carbon::create($validated['year'], 1, 1)->startOfDay();
        $endDate = Carbon::create($validated['year'], 12, 31)->endOfDay();

        $query = CourseSession::query()
            ->with(['cours', 'professeurs', 'sessionProfessors'])
            ->whereBetween('date_debut', [$startDate, $endDate]);

        if ($request->filled('professeur_id')) {
            $query->whereHas('professeurs', function ($q) {
                $q->where('professeur_id', request('professeur_id'));
            });
        }

        $sessions = $query->orderBy('date_debut')->get();

        // Group by month
        $byMonth = $sessions->groupBy(function ($session) {
            return $session->date_debut->format('Y-m');
        });

        return response()->json([
            'year' => $validated['year'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $byMonth,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
                'by_month' => $byMonth->map->count(),
            ],
        ]);
    }

    /**
     * Get professor's personal calendar.
     */
    public function professorCalendar(Professeur $professeur, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $startDate = Carbon::create($validated['year'], $validated['month'] ?? 1, 1)->startOfDay();
        $endDate = $validated['month']
            ? $startDate->clone()->endOfMonth()
            : Carbon::create($validated['year'], 12, 31)->endOfDay();

        $sessions = $professeur->sessions()
            ->with(['cours', 'sessionProfessors'])
            ->whereBetween('date_debut', [$startDate, $endDate])
            ->orderBy('date_debut')
            ->get();

        // Group by date for calendar view
        $calendar = $sessions->groupBy(function ($session) {
            return $session->date_debut->format('Y-m-d');
        });

        return response()->json([
            'professeur_id' => $professeur->id,
            'professeur_name' => "{$professeur->prenom} {$professeur->nom}",
            'year' => $validated['year'],
            'month' => $validated['month'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $calendar,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
                'by_role' => $sessions->map(function ($s) {
                    return $s->pivot->role ?? 'unknown';
                })->groupBy(fn($r) => $r)->map->count(),
            ],
        ]);
    }

    /**
     * Save calendar view preference.
     */
    public function saveView(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'vue_defaut' => 'required|in:month,week,agenda',
            'filtres' => 'nullable|array',
        ]);

        $view = SessionCalendarView::updateOrCreate(
            [
                'user_id' => $user->id,
                'nom' => $validated['nom'],
            ],
            [
                'vue_defaut' => $validated['vue_defaut'],
                'filtres' => $validated['filtres'] ?? null,
            ]
        );

        return response()->json([
            'data' => $view,
            'message' => 'Vue calendrier sauvegardée',
        ], 201);
    }

    /**
     * Get calendar view preferences for current user.
     */
    public function getViews(): JsonResponse
    {
        $user = auth()->user();

        $views = SessionCalendarView::where('user_id', $user->id)
            ->orderBy('nom')
            ->get();

        return response()->json([
            'data' => $views,
            'count' => $views->count(),
        ]);
    }

    /**
     * Delete calendar view preference.
     */
    public function deleteView(SessionCalendarView $view): JsonResponse
    {
        if ($view->user_id !== auth()->id()) {
            return response()->json([
                'error' => 'Non autorisé',
            ], 403);
        }

        $view->delete();

        return response()->json([
            'message' => 'Vue calendrier supprimée',
        ]);
    }

    /**
     * Get sessions by date range (agenda view).
     */
    public function agenda(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'professeur_id' => 'nullable|exists:professeurs,id',
            'statut' => 'nullable|array',
            'statut.*' => 'in:scheduled,in_progress,completed,cancelled',
        ]);

        $query = CourseSession::query()
            ->with(['cours', 'professeurs', 'sessionProfessors'])
            ->whereBetween('date_debut', [$validated['from_date'], $validated['to_date']]);

        if ($request->filled('professeur_id')) {
            $query->whereHas('professeurs', function ($q) {
                $q->where('professeur_id', request('professeur_id'));
            });
        }

        if ($request->filled('statut')) {
            $query->whereIn('statut', $validated['statut']);
        }

        $sessions = $query->orderBy('date_debut')->get();

        return response()->json([
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'data' => $sessions,
            'summary' => [
                'total' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
            ],
        ]);
    }
}
