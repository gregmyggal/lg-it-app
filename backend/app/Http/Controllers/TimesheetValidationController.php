<?php

namespace App\Http\Controllers;

use App\Exceptions\RegleMetierException;
use App\Models\CourseSession;
use App\Models\Timesheet;
use App\Services\TimesheetLissingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Vue directeur par session et validation en lot avec lissage (CLS-01 T3, mock-up 03). Staff uniquement. */
class TimesheetValidationController extends Controller
{
    /** R-T3-9 : sessions passées, non annulées, où au moins un professeur attendu n'a aucune saisie. */
    public function sessionsSansHeures(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $f = $request->validate([
            'classe_id' => ['sometimes', 'integer'],
            'annee_scolaire_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $maintenant = Carbon::now('Europe/Brussels');
        $aujourdhui = $maintenant->copy()->startOfDay();

        $sessions = CourseSession::query()
            ->where('statut', '!=', CourseSession::STATUT_ANNULEE)
            // Terminée : date passée, ou aujourd'hui une fois l'heure de fin atteinte, ou statut « terminée ».
            ->where(fn ($q) => $q->where('date', '<', $aujourdhui->toDateString())
                ->orWhere('statut', CourseSession::STATUT_TERMINEE)
                ->orWhere(fn ($j) => $j->where('date', $aujourdhui->toDateString())->where('heure_fin', '<=', $maintenant->format('H:i:s'))))
            ->when(isset($f['classe_id']), fn ($q) => $q->where('classe_id', $f['classe_id']))
            ->when(isset($f['annee_scolaire_id']), fn ($q) => $q->whereHas('classe', fn ($c) => $c->where('annee_scolaire_id', $f['annee_scolaire_id'])))
            ->when(isset($f['date_from']), fn ($q) => $q->where('date', '>=', $f['date_from']))
            ->when(isset($f['date_to']), fn ($q) => $q->where('date', '<=', $f['date_to']))
            ->whereHas('sessionProfesseurs', fn ($l) => $l->where('remplace', false))
            ->with(['classe.cours', 'sessionProfesseurs' => fn ($l) => $l->where('remplace', false)->with('professeur'), 'timesheets:id,course_session_id,professeur_id'])
            ->orderBy('date')->orderBy('heure_debut')->limit(500)->get();

        $items = $sessions->map(function (CourseSession $s) use ($aujourdhui) {
            $ayantEncode = $s->timesheets->pluck('professeur_id')->all();
            $sans = $s->sessionProfesseurs
                ->reject(fn ($l) => in_array($l->professeur_id, $ayantEncode, true))
                ->map(fn ($l) => ['id' => $l->professeur_id, 'nom' => trim($l->professeur->prenom.' '.$l->professeur->nom)])
                ->values();

            return $sans->isEmpty() ? null : [
                'session_id' => $s->id,
                'libelle' => $s->libelle(),
                'date' => $s->date->toDateString(),
                'classe_id' => $s->classe_id,
                'classe_libelle' => $s->classe->cours->titre,
                'professeurs_sans_heures' => $sans,
                'jours_de_retard' => (int) $s->date->startOfDay()->diffInDays($aujourdhui),
            ];
        })->filter()->values();

        return response()->json(['data' => $items]);
    }

    /**
     * R-T3-12 : applique les lissages demandés puis confirme les saisies, en UNE transaction. Aucune saisie n'est
     * exclue pour cause de lissage ; si une saisie n'est pas validable, rien n'est modifié (422 + liste).
     */
    public function validerLot(Request $request): JsonResponse
    {
        $v = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:timesheets,id'],
            'lissages' => ['sometimes', 'array', 'max:200'],
            'lissages.*.timesheet_id' => ['required', 'integer', 'exists:timesheets,id'],
            'lissages.*.date_to' => ['required', 'date_format:Y-m-d'],
            'lissages.*.montant_to_move' => ['required', 'numeric', 'min:0.01', 'max:44.02'],
        ]);
        $user = $request->user();
        $lissages = $v['lissages'] ?? [];

        return DB::transaction(function () use ($v, $user, $lissages) {
            $saisies = Timesheet::whereIn('id', $v['ids'])->lockForUpdate()->get()->keyBy('id');

            $invalides = [];
            foreach ($saisies as $t) {
                if (! $user->can('validateEntry', $t)) {
                    $invalides[] = ['id' => $t->id, 'raison' => 'Vous ne pouvez pas valider cette saisie.'];
                } elseif ($t->statut_validation !== Timesheet::STATUT_SOUMIS) {
                    $invalides[] = ['id' => $t->id, 'raison' => 'Seules les saisies soumises peuvent être validées.'];
                }
            }
            foreach ($lissages as $l) {
                if (! $saisies->has($l['timesheet_id'])) {
                    $invalides[] = ['id' => $l['timesheet_id'], 'raison' => 'Le lissage porte sur une saisie hors du lot.'];
                }
            }
            if ($invalides) {
                throw RegleMetierException::invalide(
                    'Validation impossible : '.count($invalides).' saisie(s) ne peuvent pas être validées. Rien n\'a été modifié.',
                    ['ids' => ['Saisies non validables.']],
                    ['invalides' => $invalides]
                );
            }

            $service = new TimesheetLissingService;
            foreach ($lissages as $l) {
                $origine = $saisies[$l['timesheet_id']];
                if (! $service->executeLissage($origine->id, $origine->date_prestation->toDateString(), $l['date_to'], (float) $l['montant_to_move'])) {
                    throw RegleMetierException::invalide('Lissage impossible pour la saisie #'.$origine->id.' (tarif absent ou montant supérieur à la saisie). Rien n\'a été modifié.', ['lissages' => ['Lissage impossible.']]);
                }
            }

            $lisses = collect($lissages)->pluck('timesheet_id')->unique();
            foreach ($saisies as $t) {
                $t->refresh()->update([
                    'statut_validation' => Timesheet::STATUT_CONFIRME,
                    'validated_at' => now(),
                    'validated_by' => $user->id,
                    'lissage_applique' => $t->lissage_applique || $lisses->contains($t->id),
                ]);
            }

            return response()->json(['validees' => $saisies->count(), 'lissages_appliques' => count($lissages)]);
        });
    }
}
