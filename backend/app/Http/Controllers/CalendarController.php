<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseSessionResource;
use App\Models\CourseSession;
use App\Models\SessionCalendarView;
use App\Services\CalendrierScolaireService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Calendrier des sessions (T1 : admin/directeur). Filtres : classe_id, cours_id, annee_scolaire_id.
 * La vue « calendrier d'un professeur » reviendra en T2 (professeur_classe).
 */
class CalendarController extends Controller
{
    private const FILTRES = [
        'classe_id' => ['nullable', 'integer', 'exists:classes,id'],
        'cours_id' => ['nullable', 'integer', 'exists:cours,id'],
        'annee_scolaire_id' => ['nullable', 'integer', 'exists:annees_scolaires,id'],
    ];

    public function __construct(private readonly CalendrierScolaireService $calendrier) {}

    public function month(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseSession::class);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ] + self::FILTRES);

        $debut = Carbon::create($validated['year'], $validated['month'], 1)->startOfDay();
        $fin = $debut->clone()->endOfMonth();
        $sessions = $this->sessions($validated, $debut, $fin);

        return response()->json([
            'year' => $validated['year'],
            'month' => $validated['month'],
            'start_date' => $debut->toDateString(),
            'end_date' => $fin->toDateString(),
            'data' => $this->groupe($sessions, fn ($s) => $s->date->format('Y-m-d'), $request),
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
            ],
        ]);
    }

    public function week(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseSession::class);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'week' => 'required|integer|min:1|max:53',
        ] + self::FILTRES);

        $debut = Carbon::now()->setISODate($validated['year'], $validated['week'], 1)->startOfDay();
        $fin = $debut->clone()->addDays(6)->endOfDay();
        $sessions = $this->sessions($validated, $debut, $fin);

        return response()->json([
            'year' => $validated['year'],
            'week' => $validated['week'],
            'start_date' => $debut->toDateString(),
            'end_date' => $fin->toDateString(),
            'data' => CourseSessionResource::collection($sessions)->resolve($request),
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_day' => $sessions->groupBy(fn ($s) => $s->date->format('Y-m-d'))->map->count(),
            ],
        ]);
    }

    public function year(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseSession::class);

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
        ] + self::FILTRES);

        $debut = Carbon::create($validated['year'], 1, 1)->startOfDay();
        $fin = Carbon::create($validated['year'], 12, 31)->endOfDay();
        $sessions = $this->sessions($validated, $debut, $fin);

        return response()->json([
            'year' => $validated['year'],
            'start_date' => $debut->toDateString(),
            'end_date' => $fin->toDateString(),
            'data' => $this->groupe($sessions, fn ($s) => $s->date->format('Y-m'), $request),
            'summary' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
                'by_month' => $sessions->groupBy(fn ($s) => $s->date->format('Y-m'))->map->count(),
            ],
        ]);
    }

    public function agenda(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CourseSession::class);

        $validated = $request->validate([
            'from_date' => 'required|date_format:Y-m-d',
            'to_date' => 'required|date_format:Y-m-d|after:from_date',
            'statut' => 'nullable|array',
            'statut.*' => 'in:'.implode(',', CourseSession::STATUTS),
        ] + self::FILTRES);

        $sessions = $this->sessions(
            $validated,
            Carbon::parse($validated['from_date'])->startOfDay(),
            Carbon::parse($validated['to_date'])->endOfDay(),
            fn (Builder $q) => $q->when(
                ! empty($validated['statut']),
                fn (Builder $q) => $q->whereIn('statut', $validated['statut'])
            )
        );

        return response()->json([
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
            'data' => CourseSessionResource::collection($sessions)->resolve($request),
            'summary' => [
                'total' => $sessions->count(),
                'by_status' => $sessions->groupBy('statut')->map->count(),
            ],
        ]);
    }

    /** Préférences de vue (par utilisateur) — inchangées par CLS-01. */
    public function saveView(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'vue_defaut' => 'required|in:month,week,agenda',
            'filtres' => 'nullable|array',
        ]);

        $view = SessionCalendarView::updateOrCreate(
            ['user_id' => $user->id, 'nom' => $validated['nom']],
            ['vue_defaut' => $validated['vue_defaut'], 'filtres' => $validated['filtres'] ?? null]
        );

        return response()->json([
            'data' => $view,
            'message' => 'Vue calendrier sauvegardée',
        ], 201);
    }

    public function getViews(): JsonResponse
    {
        $views = SessionCalendarView::where('user_id', auth()->id())->orderBy('nom')->get();

        return response()->json(['data' => $views, 'count' => $views->count()]);
    }

    public function deleteView(SessionCalendarView $view): JsonResponse
    {
        if ($view->user_id !== auth()->id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $view->delete();

        return response()->json(['message' => 'Vue calendrier supprimée']);
    }

    /** @return Collection<int, CourseSession> */
    private function sessions(array $filtres, Carbon $debut, Carbon $fin, ?callable $extra = null): Collection
    {
        $query = CourseSession::query()
            ->visiblePour(auth()->user())
            ->with(['classe.cours', 'sessionProfesseurs.professeur'])
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->when(! empty($filtres['classe_id']), fn (Builder $q) => $q->where('classe_id', $filtres['classe_id']))
            ->when(! empty($filtres['cours_id']), fn (Builder $q) => $q->whereHas('classe', fn ($c) => $c->where('cours_id', $filtres['cours_id'])))
            ->when(! empty($filtres['annee_scolaire_id']), fn (Builder $q) => $q->whereHas('classe', fn ($c) => $c->where('annee_scolaire_id', $filtres['annee_scolaire_id'])));

        if ($extra) {
            $extra($query);
        }

        $sessions = $query->orderBy('date')->orderBy('heure_debut')->orderBy('id')->get();
        $this->calendrier->attachAlerts($sessions);

        return $sessions;
    }

    private function groupe(Collection $sessions, callable $cle, Request $request): array
    {
        return $sessions->groupBy($cle)
            ->map(fn ($groupe) => CourseSessionResource::collection($groupe)->resolve($request))
            ->all();
    }
}
