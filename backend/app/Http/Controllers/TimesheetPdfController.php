<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\TimesheetPdf;
use App\Services\TimesheetPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** TS-01 T5 : fiche de défraiement PDF — génération (staff), lot en zip, déverrouillage (admin), téléchargement. */
class TimesheetPdfController extends Controller
{
    public function __construct(private readonly TimesheetPdfService $service) {}

    public function generer(Request $request, Professeur $professeur): JsonResponse
    {
        $this->exigerStaff($request);
        $p = $this->periode($request);

        $pdf = $this->service->generer($professeur, $p['annee'], $p['mois'], $request->user());

        return response()->json(['data' => $this->vue($pdf)], 201);
    }

    // Aperçu (staff) : même contenu que la génération, sans rien enregistrer.
    public function apercu(Request $request, Professeur $professeur): Response
    {
        $this->exigerStaff($request);
        $p = $this->periode($request);

        return response($this->service->apercu($professeur, $p['annee'], $p['mois']), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="apercu.pdf"']);
    }

    // Lot : un zip de fiches ; si un professeur n'est pas prêt, rien n'est généré (422 + liste des bloquants).
    public function lot(Request $request)
    {
        $this->exigerStaff($request);
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'professeur_ids' => ['required', 'array', 'min:1', 'max:200'],
            'professeur_ids.*' => ['integer', 'distinct', 'exists:professeurs,id'],
        ]);

        $r = $this->service->lot($v['professeur_ids'], (int) $v['annee'], (int) $v['mois'], $request->user());

        return response()->download($r['zip'], sprintf('fiches-defraiement-%04d-%02d.zip', $v['annee'], $v['mois']), ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    // Admin uniquement : rouvre un mois déjà généré (motif obligatoire, tracé) ; la génération suivante crée une version.
    public function deverrouiller(Request $request, Professeur $professeur): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Seul un administrateur peut déverrouiller un mois généré.');
        $p = $this->periode($request) + $request->validate(['motif' => ['required', 'string', 'min:3', 'max:1000']]);

        $n = $this->service->deverrouiller($professeur, $p['annee'], $p['mois'], $p['motif'], $request->user());

        return response()->json(['deverrouilles' => $n]);
    }

    public function index(Request $request, Professeur $professeur): JsonResponse
    {
        $this->exigerAcces($request, $professeur);
        $p = $this->periode($request);

        $pdfs = TimesheetPdf::with('professeur')->where(['professeur_id' => $professeur->id, 'annee' => $p['annee'], 'mois' => $p['mois']])->orderByDesc('version')->get();

        return response()->json(['data' => $pdfs->map(fn ($x) => $this->vue($x))]);
    }

    // Le staff, ou le professeur concerné pour ses propres fiches.
    public function telecharger(Request $request, TimesheetPdf $pdf)
    {
        $this->exigerAcces($request, $pdf->professeur);
        abort_unless(Storage::disk('local')->exists($pdf->chemin), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($pdf->chemin, $pdf->nomFichier(), ['Content-Type' => 'application/pdf']);
    }

    private function exigerStaff(Request $request): void
    {
        abort_unless($request->user()->isStaff(), 403, 'Action non autorisée.');
    }

    private function exigerAcces(Request $request, Professeur $professeur): void
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $professeur->user_id === $user->id, 403, 'Action non autorisée.');
    }

    private function periode(Request $request): array
    {
        $v = $request->validate([
            'annee' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mois' => ['required', 'integer', 'between:1,12'],
        ]);

        return ['annee' => (int) $v['annee'], 'mois' => (int) $v['mois']];
    }

    private function vue(TimesheetPdf $pdf): array
    {
        return ['id' => $pdf->id, 'version' => $pdf->version, 'total_eur' => $pdf->total_eur, 'generated_at' => $pdf->generated_at, 'nom_fichier' => $pdf->nomFichier()];
    }
}
