<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Services\TimesheetLissageMoisService;
use App\Services\TimesheetNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-01 T3 : lissage d'un mois d'un professeur (aperçu puis application). Staff uniquement. */
class TimesheetLissageController extends Controller
{
    public function __construct(private readonly TimesheetLissageMoisService $service, private readonly TimesheetNotifier $notifier) {}

    // Aperçu avant/après : mode « auto » (proposition pour tous les dépassements) ou « manuel » (déplacements fournis).
    public function apercu(Request $request, Professeur $professeur): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'mode' => ['required', 'in:auto,manuel'],
            'deplacements' => ['required_if:mode,manuel', 'array', 'max:100'],
            ...$this->regleDeplacements(),
        ]);

        $nonResolus = [];
        $deplacements = $v['deplacements'] ?? [];
        if ($v['mode'] === 'auto') {
            $p = $this->service->proposer($professeur, (int) $v['annee'], (int) $v['mois']);
            $deplacements = $p['deplacements'];
            $nonResolus = $p['non_resolus'];
        }

        return response()->json($this->service->apercu($professeur, (int) $v['annee'], (int) $v['mois'], $deplacements) + ['non_resolus' => $nonResolus]);
    }

    public function appliquer(Request $request, Professeur $professeur): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'deplacements' => ['required', 'array', 'min:1', 'max:100'],
            ...$this->regleDeplacements(),
            'motif' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $r = $this->service->appliquer($professeur, (int) $v['annee'], (int) $v['mois'], $v['deplacements'], $v['motif'], $request->user());

        $this->notifier->siMoisAConfirmer($professeur, (int) $v['annee'], (int) $v['mois']);

        return response()->json($r);
    }

    private function regleDeplacements(): array
    {
        return [
            'deplacements.*.timesheet_id' => ['required', 'integer'],
            'deplacements.*.date_to' => ['required', 'date_format:Y-m-d'],
            'deplacements.*.montant' => ['required', 'numeric', 'min:0.01', 'max:99999'],
        ];
    }
}
