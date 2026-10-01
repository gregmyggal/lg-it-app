<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClasseLienResource;
use App\Models\ClasseLien;
use App\Models\Cours;
use App\Models\Formation;
use App\Models\ShareCode;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;

class ShareCodeController extends Controller
{
    public function show(string $code): JsonResponse
    {
        $shareCode = ShareCode::where('code', $code)->first();

        if (! $shareCode) {
            return response()->json(['error' => 'Invalid share code'], 404);
        }

        if (! $shareCode->isValid()) {
            return response()->json(['error' => 'Share code is expired or has reached its usage limit'], 403);
        }

        $shareable = $shareCode->shareable;

        if (! $shareable || $shareable->statut !== 'publish') {
            return response()->json(['error' => 'Content not found or not published'], 404);
        }

        $data = [
            'type' => class_basename($shareable),
            'content' => $shareable,
        ];

        if ($shareable instanceof Cours) {
            // CLS-01 T4 : liens généraux + par séance (forme publique : aucun auteur, version ni archivé).
            $liens = $shareable->liensClasse()->orderByRaw('seance_numero IS NOT NULL')->orderBy('seance_numero')->orderBy('ordre')->get();
            $data['liens'] = [
                'generaux' => $liens->whereNull('seance_numero')->map(fn ($l) => ClasseLienResource::publique($l))->values(),
                'par_seance' => $liens->whereNotNull('seance_numero')->where('seance_numero', '<=', ClasseLien::NB_SEANCES)
                    ->groupBy('seance_numero')
                    ->map(fn ($groupe, $n) => ['seance_numero' => (int) $n, 'liens' => $groupe->map(fn ($l) => ClasseLienResource::publique($l))->values()])
                    ->values(),
            ];
            // Anciennes ressources pas encore reprises en liens (affichage transitoire, Q-T4-1).
            $data['ressources'] = $shareable->ressources()->whereNull('repris_at')->get();
            $data['types'] = $shareable->typesCours;
        } elseif ($shareable instanceof Stage) {
            $data['dates'] = $shareable->dates;
        } elseif ($shareable instanceof Formation) {
            $data['types'] = $shareable->typesFormation;
        }

        return response()->json($data);
    }
}
