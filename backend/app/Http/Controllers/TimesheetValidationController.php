<?php

namespace App\Http\Controllers;

use App\Exceptions\RegleMetierException;
use App\Models\CourseSession;
use App\Models\Timesheet;
use App\Services\TimesheetLissageMoisService;
use App\Services\TimesheetVersionService;
use App\Services\TimesheetNotifier;
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
            ->with(['classe', 'classePeriode.cours', 'classePeriode.periode', 'sessionProfesseurs' => fn ($l) => $l->where('remplace', false)->with('professeur'), 'timesheets:id,course_session_id,professeur_id'])
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
                'classe_libelle' => $s->classePeriode->cours->titre,
                'periode_numero' => $s->classePeriode->periode->numero,
                'libelle_complet' => $s->libelleComplet(),
                'professeurs_sans_heures' => $sans,
                'jours_de_retard' => (int) $s->date->startOfDay()->diffInDays($aujourdhui),
            ];
        })->filter()->values();

        return response()->json(['data' => $items]);
    }

    /**
     * R-T3-12 + TS-04 : valide des mois ENTIERS, professeur par professeur, en UNE transaction : lissages demandés
     * (service du mois), confirmation de toutes les saisies soumises du mois, puis version « validée » figée.
     * La validation partielle est refusée : toute saisie soumise d'un mois concerné doit figurer dans le lot. Si un mois
     * n'est pas validable, rien n'est modifié (422 + liste des saisies fautives).
     */
    public function validerLot(Request $request, TimesheetNotifier $notifier, TimesheetLissageMoisService $lissageService, TimesheetVersionService $versions): JsonResponse
    {
        $v = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:timesheets,id'],
            'lissages' => ['sometimes', 'array', 'max:200'],
            'lissages.*.timesheet_id' => ['required', 'integer', 'exists:timesheets,id'],
            'lissages.*.date_to' => ['required', 'date_format:Y-m-d'],
            'lissages.*.montant_to_move' => ['required', 'numeric', 'min:0.01'],
        ]);
        $user = $request->user();
        $lissages = $v['lissages'] ?? [];

        $reponse = DB::transaction(function () use ($v, $user, $lissages, $lissageService, $versions) {
            $saisies = Timesheet::whereIn('id', $v['ids'])->lockForUpdate()->get()->keyBy('id');
            $cle = fn (Timesheet $t) => $t->professeur_id.'|'.$t->date_prestation->format('Y-m');
            $groupes = $saisies->groupBy($cle);

            // Toutes les lignes de chaque mois concerné sont verrouillées avant de contrôler la complétude.
            $moisLignes = $groupes->map(function ($lignes) {
                $premiere = $lignes->first();
                [$debut, $fin] = TimesheetVersionService::bornes((int) $premiere->date_prestation->format('Y'), (int) $premiere->date_prestation->format('n'));

                return Timesheet::where('professeur_id', $premiere->professeur_id)->whereBetween('date_prestation', [$debut, $fin])->lockForUpdate()->get();
            });

            $invalides = [];
            foreach ($saisies as $t) {
                if (! $user->can('validateEntry', $t)) {
                    $invalides[] = ['id' => $t->id, 'raison' => 'Vous ne pouvez pas valider cette saisie.'];
                } elseif ($t->statut_validation !== Timesheet::STATUT_SOUMIS) {
                    $invalides[] = ['id' => $t->id, 'raison' => 'Seules les saisies soumises peuvent être validées.'];
                }
            }
            foreach ($moisLignes as $lignes) {
                foreach ($lignes as $t) {
                    if ($t->statut_validation === Timesheet::STATUT_SOUMIS && ! $saisies->has($t->id)) {
                        $invalides[] = ['id' => $t->id, 'raison' => 'La validation porte sur le mois entier : cette saisie soumise du mois n\'est pas dans le lot.'];
                    } elseif ($t->statut_validation === Timesheet::STATUT_CONTESTE) {
                        $invalides[] = ['id' => $t->id, 'raison' => 'Une contestation est en cours sur ce mois : traitez-la avant de valider.'];
                    }
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

            foreach ($groupes as $groupe => $lignes) {
                $premiere = $lignes->first();
                $annee = (int) $premiere->date_prestation->format('Y');
                $mois = (int) $premiere->date_prestation->format('n');
                $prof = $premiere->professeur;

                $deplacements = collect($lissages)
                    ->filter(fn ($l) => $cle($saisies[$l['timesheet_id']]) === $groupe)
                    ->map(fn ($l) => ['timesheet_id' => $l['timesheet_id'], 'date_to' => $l['date_to'], 'montant' => $l['montant_to_move']])->values()->all();
                if ($deplacements !== []) {
                    $lissageService->appliquer($prof, $annee, $mois, $deplacements, 'Lissage appliqué à la validation du mois', $user);
                }

                // Le lissage a pu créer des lignes : on confirme toutes les saisies soumises du mois, pas seulement le lot.
                [$debut, $fin] = TimesheetVersionService::bornes($annee, $mois);
                Timesheet::where('professeur_id', $prof->id)->whereBetween('date_prestation', [$debut, $fin])
                    ->where('statut_validation', Timesheet::STATUT_SOUMIS)
                    ->update(['statut_validation' => Timesheet::STATUT_CONFIRME, 'validated_at' => now(), 'validated_by' => $user->id]);

                $versions->figerValidee($prof, $annee, $mois, $user);
            }

            return response()->json(['validees' => $saisies->count(), 'mois_valides' => $groupes->count(), 'lissages_appliques' => count($lissages)]);
        });

        // Après validation : prévient chaque professeur dont le mois attend désormais sa signature.
        Timesheet::with('professeur')->whereIn('id', $v['ids'])->get()
            ->unique(fn (Timesheet $t) => $t->professeur_id.'|'.$t->date_prestation->format('Y-m'))
            ->each(fn (Timesheet $t) => $notifier->siMoisAConfirmer($t->professeur, (int) $t->date_prestation->format('Y'), (int) $t->date_prestation->format('n')));

        return $reponse;
    }
}
