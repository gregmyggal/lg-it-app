<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use App\Services\ProfesseurTarifService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfesseurTarifController extends Controller
{
    public function __construct(private readonly ProfesseurTarifService $tarifs) {}

    // Récupère tous les tarifs pour un professeur
    public function index(Request $request, Professeur $professeur)
    {
        Gate::authorize('viewAny', ProfesseurTarif::class);

        return $professeur->tarifs()->orderByDesc('date_debut')->get();
    }

    // Crée un nouveau tarif pour un professeur
    public function store(Request $request, Professeur $professeur)
    {
        Gate::authorize('create', ProfesseurTarif::class);

        $data = $request->validate([
            'tarif_horaire_eur' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        return response()->json($this->tarifs->creer($professeur, $data), 201);
    }

    // Met à jour un tarif existant
    public function update(Request $request, Professeur $professeur, ProfesseurTarif $professeurTarif)
    {
        Gate::authorize('update', $professeurTarif);

        // Vérifier que le tarif appartient bien au professeur
        if ($professeurTarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $data = $request->validate([
            'tarif_horaire_eur' => ['sometimes', 'numeric', 'min:0', 'max:999.99'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        return $this->tarifs->modifier($professeurTarif, $data);
    }

    // Termine un tarif (définit date_fin)
    public function terminate(Request $request, Professeur $professeur, ProfesseurTarif $professeurTarif)
    {
        Gate::authorize('update', $professeurTarif);

        if ($professeurTarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $data = $request->validate([
            'date_fin' => ['required', 'date'],
        ]);

        return $this->tarifs->modifier($professeurTarif, $data);
    }

    // Supprime un tarif
    public function destroy(Professeur $professeur, ProfesseurTarif $professeurTarif)
    {
        Gate::authorize('delete', $professeurTarif);

        if ($professeurTarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $this->tarifs->supprimer($professeurTarif);

        return response()->noContent();
    }

    // Récupère le tarif valide pour une date donnée
    public function effectiveAt(Request $request, Professeur $professeur)
    {
        Gate::authorize('viewAny', ProfesseurTarif::class);

        $date = $request->query('date');
        if (!$date) {
            return response()->json(['error' => 'date parameter required'], 400);
        }

        $tarif = ProfesseurTarif::effectiveAt($professeur->id, new \DateTime($date));

        if (!$tarif) {
            return response()->json(['error' => 'No effective tariff found'], 404);
        }

        return $tarif;
    }
}
