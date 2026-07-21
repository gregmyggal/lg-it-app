<?php

namespace App\Http\Controllers;

use App\Models\TypeFormation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TypeFormationController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', TypeFormation::class);

        return TypeFormation::orderBy('nom')->get();
    }

    public function show(TypeFormation $typeFormation)
    {
        Gate::authorize('view', $typeFormation);

        return $typeFormation;
    }

    public function store(Request $request)
    {
        Gate::authorize('create', TypeFormation::class);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:types_formation,slug'],
        ]);

        return response()->json(TypeFormation::create($data), 201);
    }

    public function update(Request $request, TypeFormation $typeFormation)
    {
        Gate::authorize('update', $typeFormation);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:types_formation,slug,'.$typeFormation->id],
        ]);

        $typeFormation->update($data);

        return $typeFormation;
    }

    public function destroy(TypeFormation $typeFormation)
    {
        Gate::authorize('delete', $typeFormation);

        $typeFormation->delete();

        return response()->noContent();
    }
}
