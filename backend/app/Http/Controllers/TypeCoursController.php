<?php

namespace App\Http\Controllers;

use App\Models\TypeCours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TypeCoursController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', TypeCours::class);

        return TypeCours::orderBy('nom')->get();
    }

    public function show(TypeCours $typeCours)
    {
        Gate::authorize('view', $typeCours);

        return $typeCours;
    }

    public function store(Request $request)
    {
        Gate::authorize('create', TypeCours::class);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:types_cours,slug'],
        ]);

        return response()->json(TypeCours::create($data), 201);
    }

    public function update(Request $request, TypeCours $typeCours)
    {
        Gate::authorize('update', $typeCours);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:types_cours,slug,'.$typeCours->id],
        ]);

        $typeCours->update($data);

        return $typeCours;
    }

    public function destroy(TypeCours $typeCours)
    {
        Gate::authorize('delete', $typeCours);

        $typeCours->delete();

        return response()->noContent();
    }
}
