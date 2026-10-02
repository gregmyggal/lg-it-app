<?php

namespace App\Http\Controllers;

use App\Models\TimesheetParametre;
use App\Services\TimesheetParametreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** TS-00 : plafonds de défraiement par année civile. */
class TimesheetParametreController extends Controller
{
    public function __construct(private readonly TimesheetParametreService $parametres) {}

    // Valeurs effectives de l'année demandée (défaut : année courante) + historique des modifications.
    public function show(Request $request, int $annee): JsonResponse
    {
        Gate::authorize('viewAny', TimesheetParametre::class);

        $historique = DB::table('timesheet_parametres_historique')
            ->leftJoin('users', 'users.id', '=', 'timesheet_parametres_historique.user_id')
            ->where('annee', $annee)
            ->orderByDesc('timesheet_parametres_historique.id')
            ->limit(20)
            ->get(['timesheet_parametres_historique.*', 'users.name as auteur']);

        return response()->json([
            'data' => $this->parametres->pour($annee),
            'historique' => $historique,
        ]);
    }

    public function update(Request $request, int $annee): JsonResponse
    {
        Gate::authorize('update', TimesheetParametre::class);

        abort_unless($annee >= 2020 && $annee <= 2100, 404);

        $v = $request->validate([
            'plafond_journalier_eur' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'plafond_annuel_eur' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'gte:plafond_journalier_eur'],
            'frais_deplacement_eur' => ['sometimes', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $this->parametres->enregistrer($annee, $v + ['frais_deplacement_eur' => $this->parametres->fraisDeplacement($annee)], $request->user());

        return response()->json(['data' => $this->parametres->pour($annee)]);
    }
}
