<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimesheetResource;
use App\Models\Professeur;
use App\Models\Timesheet;
use App\Rules\PasDeQuinzeMinutes;
use App\Services\TimesheetAjoutDirectionService;
use App\Services\TimesheetNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** TS-07 : la direction et l'admin ajoutent des heures (avec un commentaire par ligne) au mois en brouillon d'un professeur. */
class TimesheetAjoutDirectionController extends Controller
{
    public function store(Request $request, Professeur $professeur, TimesheetAjoutDirectionService $service, TimesheetNotifier $notifier): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'lignes' => ['required', 'array', 'min:1', 'max:20'],
            'lignes.*.date_prestation' => ['required', 'date_format:Y-m-d'],
            'lignes.*.type_activite' => ['required', Rule::in(TimesheetAjoutDirectionService::TYPES)],
            'lignes.*.nombre_heures' => ['required', 'numeric', 'between:0.5,24', new PasDeQuinzeMinutes],
            'lignes.*.commentaire' => ['required', 'string', 'min:3', 'max:500'],
        ], ['lignes.*.commentaire.required' => 'Le commentaire est obligatoire pour chaque ligne.', 'lignes.*.commentaire.min' => 'Le commentaire doit faire au moins 3 caractères.']);

        $creees = $service->ajouter($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), $v['lignes']);
        $notifier->heuresAjoutees($professeur, (int) $v['annee'], (int) $v['mois'], $request->user(), $creees->count(), round((float) $creees->sum('nombre_heures'), 2));

        return response()->json(['lignes' => TimesheetResource::collection(Timesheet::with('ajoutePar')->whereIn('id', $creees->pluck('id'))->orderBy('id')->get())->resolve($request)], 201);
    }
}
