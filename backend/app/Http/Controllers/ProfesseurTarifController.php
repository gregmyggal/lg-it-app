<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\ProfesseurTarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfesseurTarifController extends Controller
{
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
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
        ]);

        $tarif = $professeur->tarifs()->create($data);

        return response()->json($tarif, 201);
    }

    // Met à jour un tarif existant
    public function update(Request $request, Professeur $professeur, ProfesseurTarif $tarif)
    {
        Gate::authorize('update', $tarif);

        // Vérifier que le tarif appartient bien au professeur
        if ($tarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $data = $request->validate([
            'tarif_horaire_eur' => ['sometimes', 'numeric', 'min:0', 'max:999.99'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['nullable', 'date', 'after:date_debut'],
        ]);

        $tarif->update($data);

        return $tarif;
    }

    // Termine un tarif (définit date_fin)
    public function terminate(Request $request, Professeur $professeur, ProfesseurTarif $tarif)
    {
        Gate::authorize('update', $tarif);

        if ($tarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $data = $request->validate([
            'date_fin' => ['required', 'date', 'after:date_debut'],
        ]);

        $tarif->update($data);

        return $tarif;
    }

    // Supprime un tarif
    public function destroy(Professeur $professeur, ProfesseurTarif $tarif)
    {
        Gate::authorize('delete', $tarif);

        if ($tarif->professeur_id !== $professeur->id) {
            abort(404);
        }

        $tarif->delete();

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
