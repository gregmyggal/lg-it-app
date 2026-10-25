<?php

namespace App\Http\Controllers;

use App\Models\CourseSession;
use App\Services\TimesheetService;
use App\Services\TimesheetSyntheseMoisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** « Aujourd'hui » de la direction (UI-M6) : séances du jour et du lendemain avec leurs professeurs, et les alertes. */
class AccueilDirectionController extends Controller
{
    public function __construct(private readonly TimesheetService $timesheets, private readonly TimesheetSyntheseMoisService $synthese) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $maintenant = Carbon::now('Europe/Brussels');
        $demain = $maintenant->copy()->addDay();

        $seances = CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereBetween('date', [$maintenant->toDateString(), $demain->toDateString()])
            ->with(['classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs.professeur'])
            ->orderBy('date')->orderBy('heure_debut')->get();

        $sansProfesseur = CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereBetween('date', [$maintenant->toDateString(), $maintenant->copy()->addDays(7)->toDateString()])
            ->whereDoesntHave('sessionProfesseurs', fn ($l) => $l->where('remplace', false))
            ->count();

        return response()->json([
            'date' => $maintenant->toDateString(),
            'seances' => $seances->map(fn (CourseSession $s) => $this->carte($s, $maintenant))->values(),
            'alertes' => [
                'sans_professeur' => $sansProfesseur,
                'timesheets_a_valider' => $this->synthese->synthese($maintenant->year, $maintenant->month, $request->user())['kpis']['a_valider'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function carte(CourseSession $s, Carbon $maintenant): array
    {
        $date = $s->date->toDateString();
        $fin = substr($s->heure_fin, 0, 5);

        return [
            'id' => $s->id,
            'classe_id' => $s->classe_id,
            'classe_libelle' => $s->classePeriode->cours->titre ?? '',
            'libelle_complet' => $s->libelleComplet(),
            'date' => $date,
            'heure_debut' => substr($s->heure_debut, 0, 5),
            'heure_fin' => $fin,
            'lieu' => $s->lieu,
            'est_aujourdhui' => $date === $maintenant->toDateString(),
            'en_cours' => $this->timesheets->aCommence($s) && ($date > $maintenant->toDateString() || $fin > $maintenant->format('H:i')),
            'terminee' => $date === $maintenant->toDateString() && $fin <= $maintenant->format('H:i'),
            'professeurs' => $s->sessionProfesseurs->where('remplace', false)->map(fn ($l) => [
                'id' => $l->professeur_id,
                'nom' => trim($l->professeur->prenom.' '.$l->professeur->nom),
                'telephone' => $l->professeur->telephone,
                'role' => $l->role,
                'origine' => $l->origine,
            ])->values(),
        ];
    }
}
