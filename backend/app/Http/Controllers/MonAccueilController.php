<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Services\MonAccueilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** « Aujourd'hui » du professeur (UI-M3) : une seule requête pour la page d'arrivée mobile. */
class MonAccueilController extends Controller
{
    public function __construct(private readonly MonAccueilService $service) {}

    public function show(Request $request): JsonResponse
    {
        Gate::authorize('create', Timesheet::class);

        return response()->json($this->service->pour($request->user()->professeur));
    }
}
