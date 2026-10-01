<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Timesheet;
use App\Services\TimesheetLissingService;
use App\Services\TimesheetPdfService;
use App\Services\TimesheetService;
use App\Services\TimesheetSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TimesheetController extends Controller
{
    /** Liste filtrable ; un professeur ne voit que ses propres saisies (et ses propres montants). */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $filtres = $request->validate([
            'professeur_id' => ['sometimes', 'integer'],
            'classe_id' => ['sometimes', 'integer'],
            'course_session_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d'],
            'statut' => ['sometimes', Rule::in(Timesheet::STATUTS)],
            'type_activite' => ['sometimes', Rule::in(TimesheetService::TYPES)],
        ]);

        $user = $request->user();

        $query = Timesheet::with('professeur', 'cours', 'session.classe.cours');

        if (! $user->isStaff()) {
            $query->where('professeur_id', $user->professeur?->id ?? 0);
        } elseif (isset($filtres['professeur_id'])) {
            $query->where('professeur_id', $filtres['professeur_id']);
        }

        $query
            ->when(isset($filtres['classe_id']), fn ($q) => $q->whereHas('session', fn ($s) => $s->where('classe_id', $filtres['classe_id'])))
            ->when(isset($filtres['course_session_id']), fn ($q) => $q->where('course_session_id', $filtres['course_session_id']))
            ->when(isset($filtres['date_from']), fn ($q) => $q->where('date_prestation', '>=', $filtres['date_from']))
            ->when(isset($filtres['date_to']), fn ($q) => $q->where('date_prestation', '<=', $filtres['date_to']))
            ->when(isset($filtres['statut']), fn ($q) => $q->where('statut_validation', $filtres['statut']))
            ->when(isset($filtres['type_activite']), fn ($q) => $q->where('type_activite', $filtres['type_activite']));

        // Forme historique conservée : un tableau (pas d'enveloppe `data`).
        return response()->json(TimesheetResource::collection($query->latest('date_prestation')->latest('id')->get())->resolve($request));
    }

    /** Encodage par le professeur connecté : lié à une session ou libre (CLS-01 T3, R-T3-1 à 5). */
    public function store(StoreTimesheetRequest $request, TimesheetService $service)
    {
        $timesheet = $service->creer($request->user(), $request->validated());

        return (new TimesheetResource($timesheet->load('professeur', 'cours', 'session.classe.cours')))
            ->response()->setStatusCode(201);
    }

    public function show(Timesheet $timesheet)
    {
        Gate::authorize('view', $timesheet);

        return new TimesheetResource($timesheet->load('professeur', 'cours', 'session.classe.cours'));
    }

    // Verrouillé (US-302/311) : seul l'admin modifie une saisie soumise/validée,
    // le professeur ne modifie que ses brouillons (cf. TimesheetPolicy::update).
    // Le rattachement à la session n'est pas modifiable (R-T3-4).
    public function update(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('update', $timesheet);

        $data = $request->validate([
            'date_prestation' => ['sometimes', 'date'],
            'nombre_heures' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'cours_id' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string'],
        ]);

        if ($timesheet->course_session_id !== null) {
            unset($data['date_prestation'], $data['cours_id']);
        }

        $timesheet->update($data);

        return new TimesheetResource($timesheet->load('professeur', 'cours', 'session.classe.cours'));
    }

    public function destroy(Timesheet $timesheet)
    {
        Gate::authorize('delete', $timesheet);

        $timesheet->delete();

        return response()->noContent();
    }

    // Transition brouillon → soumis (US-302), réservée au professeur propriétaire.
    public function submit(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('submit', $timesheet);

        $timesheet->update(['statut_validation' => Timesheet::STATUT_SOUMIS]);

        return new TimesheetResource($timesheet->load('professeur', 'cours', 'session.classe.cours'));
    }

    // Transition soumis → confirmé (US-311), réservée au directeur/admin.
    public function validateEntry(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('validateEntry', $timesheet);

        $data = $request->validate([
            'lissage_applique' => ['sometimes', 'boolean'],
        ]);

        $timesheet->update([
            'statut_validation' => 'confirme',
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
            'lissage_applique' => $data['lissage_applique'] ?? false,
        ]);

        return $timesheet;
    }

    // Récupère la suggestion de lissage pour un jour en dépassement
    public function proposeLissage(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $service = new TimesheetLissingService;
        $proposal = $service->proposeLissage(
            $timesheet->professeur_id,
            $timesheet->date_prestation->format('Y-m-d'),
            $request->input('year'),
            $request->input('month')
        );

        return response()->json($proposal);
    }

    // Effectue un lissage: déplace des heures d'un jour à l'autre
    public function applyLissage(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('validateEntry', $timesheet);

        $data = $request->validate([
            'date_to' => ['required', 'date'],
            'montant_to_move' => ['required', 'numeric', 'min:0.01', 'max:44.02'],
        ]);

        $service = new TimesheetLissingService;
        $success = $service->executeLissage(
            $timesheet->id,
            $timesheet->date_prestation->format('Y-m-d'),
            $data['date_to'],
            $data['montant_to_move']
        );

        if (! $success) {
            return response()->json(['error' => 'Lissage impossible'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lissage effectué',
            'timesheet' => $timesheet->fresh(),
        ]);
    }

    // Aperçu des données pour le PDF de défraiement
    public function previewPdf(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $request->validate([
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $service = new TimesheetSignatureService;
        $pdfData = $service->preparePdfData(
            $request->input('professeur_id'),
            $request->input('year'),
            $request->input('month')
        );

        return response()->json($pdfData);
    }

    // Signature d'un timesheet par le professeur
    public function sign(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('view', $timesheet);

        $user = $request->user();
        if (! $user->isProfesseur()) {
            abort(403, 'Seul un professeur peut signer');
        }

        $service = new TimesheetSignatureService;
        if (! $service->signTimesheet($timesheet, $user)) {
            return response()->json(['error' => 'Impossible de signer ce timesheet'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Timesheet signé',
            'timesheet' => $timesheet->fresh(),
        ]);
    }

    // Vérifie si un mois entier peut être signé
    public function canSignMonth(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $request->validate([
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $service = new TimesheetSignatureService;
        $result = $service->canSignMonth(
            $request->input('professeur_id'),
            $request->input('year'),
            $request->input('month')
        );

        return response()->json($result);
    }

    // Signe tous les timesheets d'un mois
    public function signMonth(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $request->validate([
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $user = $request->user();

        // Vérification: prof ne peut signer que ses propres heures
        if ($user->isProfesseur() && $user->professeur?->id !== $request->input('professeur_id')) {
            abort(403);
        }

        $service = new TimesheetSignatureService;
        if (! $service->signMonth(
            $request->input('professeur_id'),
            $request->input('year'),
            $request->input('month'),
            $user
        )) {
            return response()->json(['error' => 'Impossible de signer ce mois'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mois signé avec succès',
        ]);
    }

    // Phase 2: Génère le PDF de défraiement pour un mois entier
    public function generatePdf(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $data = $request->validate([
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        // Vérification: directeur ne peut générer que pour ses propres professeurs
        $user = $request->user();
        if (! $user->isAdmin()) {
            // TODO: vérifier que le directeur gère ce professeur
        }

        $service = new TimesheetPdfService(new TimesheetLissingService);
        $result = $service->generateMonthlyPdf(
            $data['professeur_id'],
            $data['year'],
            $data['month'],
            $user->id
        );

        return response()->json($result);
    }

    // Phase 2: Télécharge le PDF généré
    public function downloadPdf(Request $request)
    {
        $data = $request->validate([
            'professeur_id' => ['required', 'integer', 'exists:professeurs,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        Gate::authorize('viewAny', Timesheet::class);

        $service = new TimesheetPdfService(new TimesheetLissingService);
        $pdfPath = $service->getPdfPath(
            $data['professeur_id'],
            $data['year'],
            $data['month']
        );

        if (! $pdfPath || ! Storage::disk('local')->exists($pdfPath)) {
            return response()->json(['error' => 'PDF non trouvé'], 404);
        }

        return Storage::disk('local')->download($pdfPath);
    }
}
