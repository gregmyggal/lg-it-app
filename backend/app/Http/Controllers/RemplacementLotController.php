<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemplacementLotRequest;
use App\Models\CourseSession;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Services\SessionReplacementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** UI-M6 : séances d'un professeur et remplacement atomique (tout ou rien) sur plusieurs séances. */
class RemplacementLotController extends Controller
{
    public function __construct(private readonly SessionReplacementService $remplacements) {}

    /** Séances à venir (ou d'une période) où le professeur intervient encore : base du choix « remplacer sur plusieurs séances ». */
    public function seances(Request $request, Professeur $professeur): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        $v = $request->validate(['du' => ['nullable', 'date'], 'au' => ['nullable', 'date', 'after_or_equal:du']]);
        $du = $v['du'] ?? now('Europe/Brussels')->toDateString();
        $au = $v['au'] ?? now('Europe/Brussels')->addDays(6)->toDateString();

        $sessions = CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            ->whereBetween('date', [$du, $au])
            ->whereHas('sessionProfesseurs', fn ($l) => $l->where('professeur_id', $professeur->id)->where('remplace', false))
            ->with(['classePeriode.cours', 'classePeriode.periode'])
            ->orderBy('date')->orderBy('heure_debut')->get();

        $encodees = Timesheet::where('professeur_id', $professeur->id)->whereIn('course_session_id', $sessions->pluck('id'))->pluck('course_session_id')->flip();

        return response()->json(['data' => $sessions->map(fn (CourseSession $s) => [
            'id' => $s->id,
            'classe_id' => $s->classe_id,
            'classe_libelle' => $s->classePeriode->cours->titre ?? '',
            'libelle_complet' => $s->libelleComplet(),
            'date' => $s->date->toDateString(),
            'heure_debut' => substr($s->heure_debut, 0, 5),
            'heure_fin' => substr($s->heure_fin, 0, 5),
            'lieu' => $s->lieu,
            'heures_encodees' => $encodees->has($s->id),
        ])->values()]);
    }

    /** Aperçu : mêmes règles que l'application, aucune écriture. */
    public function apercu(RemplacementLotRequest $request, Professeur $professeur): JsonResponse
    {
        $r = $this->remplacements->remplacerPlusieurs($professeur, $request->input('session_ids'), $request->integer('remplacant_id'), simulation: true);

        return response()->json(['data' => ['seances' => count($r['sessions']), 'avertissements' => $r['avertissements']]]);
    }

    /** Remplacement atomique, puis un seul mail/notification au remplaçant. */
    public function appliquer(RemplacementLotRequest $request, Professeur $professeur): JsonResponse
    {
        $r = $this->remplacements->remplacerPlusieurs($professeur, $request->input('session_ids'), $request->integer('remplacant_id'));
        $this->remplacements->prevenirRemplacant($r['sessions'], $professeur, $request->integer('remplacant_id'));

        return response()->json(['data' => ['seances' => count($r['sessions']), 'avertissements' => $r['avertissements']]]);
    }
}
