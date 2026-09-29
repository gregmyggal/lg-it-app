<?php

namespace App\Http\Controllers;

use App\Models\ShareCode;
use Illuminate\Http\JsonResponse;

class ShareCodeController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $shareCode = ShareCode::where('code', $code)->first();

        if (!$shareCode) {
            return response()->json(['error' => 'Invalid share code'], 404);
        }

        if (!$shareCode->isValid()) {
            return response()->json(['error' => 'Share code is expired or has reached its usage limit'], 403);
        }

        $shareable = $shareCode->shareable;

        if (!$shareable || $shareable->statut !== 'publish') {
            return response()->json(['error' => 'Content not found or not published'], 404);
        }

        $data = [
            'type' => class_basename($shareable),
            'content' => $shareable,
        ];

        if ($shareable instanceof \App\Models\Cours) {
            $data['ressources'] = $shareable->ressources;
            $data['types'] = $shareable->typesCours;
        } elseif ($shareable instanceof \App\Models\Stage) {
            $data['dates'] = $shareable->dates;
        } elseif ($shareable instanceof \App\Models\Formation) {
            $data['types'] = $shareable->typesFormation;
        }

        return response()->json($data);
    }
}
