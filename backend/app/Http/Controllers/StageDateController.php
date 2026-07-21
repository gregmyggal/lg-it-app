<?php

namespace App\Http\Controllers;

use App\Models\Stage;
use App\Models\StageDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StageDateController extends Controller
{
    public function store(Request $request, Stage $stage)
    {
        Gate::authorize('update', $stage);

        $data = $request->validate([
            'date_session' => ['required', 'date'],
        ]);

        return response()->json($stage->dates()->create($data), 201);
    }

    public function destroy(Stage $stage, StageDate $stageDate)
    {
        Gate::authorize('update', $stage);

        $stageDate->delete();

        return response()->noContent();
    }
}
