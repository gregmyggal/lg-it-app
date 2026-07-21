<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $user = $request->user();

        $query = Timesheet::with('professeur', 'cours');

        if (! $user->isStaff()) {
            $query->where('professeur_id', $user->professeur?->id ?? 0);
        }

        return $query->latest('date_prestation')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'professeur_id' => ['required', 'integer'],
            'date_prestation' => ['required', 'date'],
            'nombre_heures' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'cours_id' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string'],
        ]);

        Gate::authorize('create', [Timesheet::class, $data['professeur_id']]);

        $timesheet = Timesheet::create($data + ['statut_validation' => 'brouillon']);

        return response()->json($timesheet, 201);
    }

    public function show(Timesheet $timesheet)
    {
        Gate::authorize('view', $timesheet);

        return $timesheet->load('professeur', 'cours');
    }

    // Verrouillé (US-302/311) : seul l'admin modifie une saisie soumise/validée,
    // le professeur ne modifie que ses brouillons (cf. TimesheetPolicy::update).
    public function update(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('update', $timesheet);

        $data = $request->validate([
            'date_prestation' => ['sometimes', 'date'],
            'nombre_heures' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'cours_id' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string'],
        ]);

        $timesheet->update($data);

        return $timesheet;
    }

    public function destroy(Timesheet $timesheet)
    {
        Gate::authorize('delete', $timesheet);

        $timesheet->delete();

        return response()->noContent();
    }

    // Transition brouillon → soumis (US-302), réservée au professeur propriétaire.
    public function submit(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('submit', $timesheet);

        $timesheet->update(['statut_validation' => 'soumis']);

        return $timesheet;
    }

    // Transition soumis → validé (US-311), réservée au directeur/admin.
    public function validateEntry(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('validateEntry', $timesheet);

        $timesheet->update([
            'statut_validation' => 'valide',
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        return $timesheet;
    }
}
