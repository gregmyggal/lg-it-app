<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\Timesheet;
use App\Services\TimesheetConfirmationService;
use App\Services\TimesheetNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** TS-01 T4 : reconfirmation du mois par le professeur (accepter/contester) et traitement par la direction. */
class TimesheetConfirmationController extends Controller
{
    public function __construct(
        private readonly TimesheetConfirmationService $service,
        private readonly TimesheetNotifier $notifier,
    ) {}

    // Professeur : ce qui a été ajusté, contestation en cours, signature possible.
    public function etat(Request $request): JsonResponse
    {
        Gate::authorize('create', Timesheet::class);
        $v = $this->periode($request);

        return response()->json($this->service->etat($request->user()->professeur, $v['annee'], $v['mois']));
    }

    // Professeur : conteste les saisies confirmées non signées de son mois (motif obligatoire).
    public function contester(Request $request): JsonResponse
    {
        Gate::authorize('create', Timesheet::class);
        $v = $this->periode($request) + $request->validate(['motif' => ['required', 'string', 'min:3', 'max:1000']]);
        $prof = $request->user()->professeur;

        $n = $this->service->contester($prof, $v['annee'], $v['mois'], $v['motif'], $request->user());
        $this->notifier->contestation($prof, $v['annee'], $v['mois'], $v['motif']);

        return response()->json(['contestees' => $n]);
    }

    // Staff : traite la contestation (saisies contestées → soumis) avec une réponse au professeur.
    public function traiter(Request $request, Professeur $professeur): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $this->periode($request) + $request->validate(['reponse' => ['required', 'string', 'min:3', 'max:1000']]);

        $n = $this->service->traiterContestation($professeur, $v['annee'], $v['mois'], $v['reponse'], $request->user());
        $this->notifier->contestationTraitee($professeur, $v['annee'], $v['mois'], $v['reponse']);

        return response()->json(['traitees' => $n]);
    }

    private function periode(Request $request): array
    {
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
        ]);

        return ['annee' => (int) $v['annee'], 'mois' => (int) $v['mois']];
    }
}
