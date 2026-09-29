<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Services\TimesheetLissingService;
use App\Services\TimesheetSignatureService;
use App\Services\TimesheetPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Timesheet::class);

        $user = $request->user();

        $query = Timesheet::with('professeur', 'cours');

        if (! $user->isStaff()) {
            $query->where('professeur_id', $user->professeur?->id ?? 0);
        }

        return $query->latest('date_prestation')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'professeur_id' => ['required', 'integer'],
            'date_prestation' => ['required', 'date'],
            'nombre_heures' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'cours_id' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string'],
        ]);

        Gate::authorize('create', [Timesheet::class, $data['professeur_id']]);

        $timesheet = Timesheet::create($data + ['statut_validation' => 'brouillon']);

        return response()->json($timesheet, 201);
    }

    public function show(Timesheet $timesheet)
    {
        Gate::authorize('view', $timesheet);

        return $timesheet->load('professeur', 'cours');
    }

    // Verrouillé (US-302/311) : seul l'admin modifie une saisie soumise/validée,
    // le professeur ne modifie que ses brouillons (cf. TimesheetPolicy::update).
    public function update(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('update', $timesheet);

        $data = $request->validate([
            'date_prestation' => ['sometimes', 'date'],
            'nombre_heures' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'cours_id' => ['nullable', 'integer'],
            'commentaire' => ['nullable', 'string'],
        ]);

        $timesheet->update($data);

        return $timesheet;
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

        $timesheet->update(['statut_validation' => 'soumis']);

        return $timesheet;
    }

    // Transition soumis → confirmé (US-311), réservée au directeur/admin.
    public function validateEntry(Request $request, Timesheet $timesheet)
    {
        Gate::authorize('validateEntry', $timesheet);

        $data = $request->validate([
            'lissage_applique' => ['sometimes', 'boolean'],
        ]);

        $timesheet->update([
            'statut_validation' => 'confirmé',
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

        $service = new TimesheetLissingService();
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

        $service = new TimesheetLissingService();
        $success = $service->executeLissage(
            $timesheet->id,
            $timesheet->date_prestation->format('Y-m-d'),
            $data['date_to'],
            $data['montant_to_move']
        );

        if (!$success) {
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

        $service = new TimesheetSignatureService();
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
        if (!$user->isProfesseur()) {
            abort(403, 'Seul un professeur peut signer');
        }

        $service = new TimesheetSignatureService();
        if (!$service->signTimesheet($timesheet, $user)) {
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

        $service = new TimesheetSignatureService();
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

        $service = new TimesheetSignatureService();
        if (!$service->signMonth(
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
        if (!$user->isAdmin()) {
            // TODO: vérifier que le directeur gère ce professeur
        }

        $service = new TimesheetPdfService(new TimesheetLissingService());
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

        $service = new TimesheetPdfService(new TimesheetLissingService());
        $pdfPath = $service->getPdfPath(
            $data['professeur_id'],
            $data['year'],
            $data['month']
        );

        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return response()->json(['error' => 'PDF non trouvé'], 404);
        }

        return Storage::disk('local')->download($pdfPath);
    }
}
