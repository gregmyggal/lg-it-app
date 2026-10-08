<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimesheetResource;
use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Models\Timesheet;
use App\Models\TimesheetAudit;
use App\Models\TimesheetPdf;
use App\Services\EmployeurMoisService;
use App\Services\SignatureNumeriqueService;
use App\Services\TarifResolver;
use App\Services\TimesheetParametreService;
use App\Services\TimesheetPdfService;
use App\Services\TimesheetSyntheseMoisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** TS-01 T3 : détail d'un professeur pour un mois (saisies, jauge par jour, historique). Staff uniquement. */
class TimesheetDetailMoisController extends Controller
{
    public function show(Request $request, Professeur $professeur, TimesheetSyntheseMoisService $synthese, TimesheetParametreService $parametres, TarifResolver $tarifs, TimesheetPdfService $pdfs, SignatureNumeriqueService $signatures, EmployeurMoisService $employeurs): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');

        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
        ]);
        $debut = Carbon::create((int) $v['annee'], (int) $v['mois'], 1);
        $fin = $debut->copy()->endOfMonth();

        $lignes = Timesheet::with('professeur', 'cours', 'session.classe', 'session.classePeriode.cours', 'session.classePeriode.periode')
            ->where('professeur_id', $professeur->id)
            ->whereBetween('date_prestation', [$debut->toDateString(), $fin->toDateString()])
            ->orderBy('date_prestation')->orderBy('id')->get();

        $plafond = $parametres->plafondJournalier((int) $v['annee']);
        $tarifsProf = ProfesseurTarif::where('professeur_id', $professeur->id)->get();
        $jours = $lignes->where('statut_validation', '!=', Timesheet::STATUT_BROUILLON)
            ->groupBy(fn (Timesheet $t) => $t->date_prestation->toDateString())
            ->map(fn ($l, $date) => ['date' => $date, 'montant' => round((float) $l->sum(fn (Timesheet $t) => (float) $tarifs->montant($t, $tarifsProf)), 2)])
            ->map(fn ($j) => $j + ['depasse' => $j['montant'] > $plafond])
            ->values();

        $historique = TimesheetAudit::with('auteur:id,name', 'timesheet:id,date_prestation,type_activite')
            ->whereIn('timesheet_id', $lignes->pluck('id'))
            ->latest('id')->get()
            ->map(fn (TimesheetAudit $a) => [
                'id' => $a->id,
                'timesheet_id' => $a->timesheet_id,
                'date_prestation' => $a->timesheet?->date_prestation?->toDateString(),
                'action' => $a->action,
                'avant' => $a->avant,
                'apres' => $a->apres,
                'motif' => $a->motif,
                'auteur' => $a->auteur?->name,
                'created_at' => $a->created_at,
            ]);

        $resume = collect($synthese->synthese((int) $v['annee'], (int) $v['mois'], $request->user())['professeurs'])->firstWhere('professeur_id', $professeur->id);

        return response()->json([
            'professeur' => ['id' => $professeur->id, 'nom' => trim($professeur->prenom.' '.$professeur->nom)],
            'periode' => ['annee' => (int) $v['annee'], 'mois' => (int) $v['mois'], 'plafond_journalier_eur' => $plafond],
            'resume' => $resume,
            'employeur' => $employeurs->pourProfesseur($professeur, (int) $v['annee'], (int) $v['mois'], $request->user()),
            'lignes' => TimesheetResource::collection($lignes)->resolve($request),
            'jours' => $jours,
            'historique' => $historique,
            'remise_brouillon' => app(\App\Services\TimesheetConfirmationService::class)->etatRemiseBrouillon($professeur, (int) $v['annee'], (int) $v['mois']),
            'signature' => ($sig = $signatures->derniere($professeur, (int) $v['annee'], (int) $v['mois'])) ? $signatures->preuve($sig, $request->user()) : null,
            'pdf' => [
                'bloquants' => $pdfs->bloquants($professeur, (int) $v['annee'], (int) $v['mois']),
                'versions' => TimesheetPdf::where(['professeur_id' => $professeur->id, 'annee' => (int) $v['annee'], 'mois' => (int) $v['mois']])
                    ->orderByDesc('version')->get(['id', 'version', 'total_eur', 'generated_at']),
            ],
        ]);
    }
}
