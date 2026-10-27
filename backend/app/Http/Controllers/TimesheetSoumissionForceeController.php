<?php

namespace App\Http\Controllers;

use App\Exceptions\RegleMetierException;
use App\Models\Professeur;
use App\Services\TimesheetNotifier;
use App\Services\TimesheetSoumissionForceeService;
use App\Services\TimesheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** TS-05 : l'admin et la direction soumettent le mois d'un professeur en l'état (message obligatoire). */
class TimesheetSoumissionForceeController extends Controller
{
    public function store(Request $request, Professeur $professeur, TimesheetService $service, TimesheetNotifier $notifier): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'motif' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $resume = $service->forcerSoumission($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), trim($v['motif']));
        $notifier->soumissionForcee($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), $resume, trim($v['motif']));

        return response()->json($resume);
    }

    private const REGLES_LOT = [
        'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
        'mois' => ['required', 'integer', 'between:1,12'],
        'professeur_ids' => ['required', 'array', 'min:1', 'max:50'],
        'professeur_ids.*' => ['integer', 'distinct', 'exists:professeurs,id'],
    ];

    /** Aperçu de la soumission groupée : éligibilité et avertissements de chaque professeur sélectionné (rien n'est modifié). */
    public function apercuLot(Request $request, TimesheetSoumissionForceeService $etats): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate(self::REGLES_LOT, ['professeur_ids.max' => 'Sélectionnez 50 professeurs au plus.']);

        $data = Professeur::whereIn('id', $v['professeur_ids'])->orderBy('nom')->orderBy('prenom')->get()
            ->map(fn (Professeur $p) => ['professeur_id' => $p->id, 'nom' => trim($p->prenom.' '.$p->nom)] + $etats->etat($p, (int) $v['annee'], (int) $v['mois']));

        return response()->json(['data' => $data]);
    }

    /**
     * Soumission groupée : chaque professeur est traité dans sa propre transaction (pas de tout ou rien : l'échec ou
     * l'inéligibilité de l'un n'empêche pas les autres). Réponse : un résultat par professeur.
     */
    public function lot(Request $request, TimesheetService $service, TimesheetNotifier $notifier): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate(self::REGLES_LOT + ['motif' => ['required', 'string', 'min:3', 'max:500']], ['professeur_ids.max' => 'Sélectionnez 50 professeurs au plus.']);
        $annee = (int) $v['annee'];
        $mois = (int) $v['mois'];
        $motif = trim($v['motif']);

        $resultats = Professeur::whereIn('id', $v['professeur_ids'])->orderBy('nom')->orderBy('prenom')->get()->map(function (Professeur $p) use ($service, $notifier, $request, $annee, $mois, $motif) {
            $base = ['professeur_id' => $p->id, 'nom' => trim($p->prenom.' '.$p->nom)];
            try {
                $resume = $service->forcerSoumission($p, $annee, $mois, $request->user(), $motif);
            } catch (RegleMetierException $e) {
                return $base + ['statut' => 'ignore', 'raison' => $e->status === 409 ? 'Ce mois est déjà soumis.' : $e->getMessage()];
            }
            $notifier->soumissionForcee($p, $annee, $mois, $request->user(), $resume, $motif);

            return $base + ['statut' => 'soumis'] + $resume;
        })->values();

        return response()->json([
            'soumis' => $resultats->where('statut', 'soumis')->count(),
            'ignores' => $resultats->where('statut', 'ignore')->count(),
            'resultats' => $resultats,
        ]);
    }
}
