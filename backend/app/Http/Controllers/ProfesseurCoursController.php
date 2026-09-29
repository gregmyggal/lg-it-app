<?php

namespace App\Http\Controllers;

use App\Models\Cours;
use App\Models\Professeur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfesseurCoursController extends Controller
{
    /**
     * Assigner un ou plusieurs cours à un professeur.
     *
     * POST /professeurs/{professeur}/cours
     * Body: { courses: [{ id, role?, date_debut?, date_fin? }, ...] }
     */
    public function assignCoursesToProfesseur(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'courses' => 'required|array',
            'courses.*.id' => 'required|exists:cours,id',
            'courses.*.role' => 'sometimes|in:principal,co-enseignant,remplaçant',
            'courses.*.date_debut' => 'sometimes|date',
            'courses.*.date_fin' => 'nullable|date',
        ]);

        foreach ($data['courses'] as $courseData) {
            $courseId = $courseData['id'];
            $role = $courseData['role'] ?? 'co-enseignant';
            $dateDebut = $courseData['date_debut'] ?? today();
            $dateFin = $courseData['date_fin'] ?? null;

            // Sync ou update si existe déjà
            $professeur->cours()->syncWithoutDetaching([
                $courseId => [
                    'role' => $role,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Cours assignés avec succès',
            'professeur' => $professeur->load('cours'),
        ]);
    }

    /**
     * Modifier une assignation (rôle, dates) d'un professeur à un cours.
     *
     * PUT /professeurs/{professeur}/cours/{cours}
     * Body: { role?, date_debut?, date_fin? }
     */
    public function updateProfesseurCours(Request $request, Professeur $professeur, Cours $cours)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'role' => 'sometimes|in:principal,co-enseignant,remplaçant',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'nullable|date',
        ]);

        // Vérifier que l'assignation existe
        $exists = $professeur->coursHistorique()
            ->where('cours_id', $cours->id)
            ->exists();

        if (!$exists) {
            return response()->json(['error' => 'Assignation non trouvée'], 404);
        }

        $professeur->cours()->updateExistingPivot($cours->id, $data);

        return response()->json([
            'message' => 'Assignation mise à jour',
            'professeur' => $professeur->load('cours'),
        ]);
    }

    /**
     * Retirer un professeur d'un cours (soft delete via date_fin).
     *
     * DELETE /professeurs/{professeur}/cours/{cours}
     */
    public function removeProfesseurFromCours(Professeur $professeur, Cours $cours)
    {
        Gate::authorize('delete', $professeur);

        // Au lieu de detach, on set date_fin = aujourd'hui
        $professeur->coursHistorique()->updateExistingPivot($cours->id, [
            'date_fin' => today(),
        ]);

        return response()->json([
            'message' => 'Professeur retiré du cours',
            'professeur' => $professeur->load('cours'),
        ]);
    }

    /**
     * Lister les professeurs assignés à un cours.
     *
     * GET /cours/{cours}/professeurs
     */
    public function listProfesseursByCours(Cours $cours)
    {
        Gate::authorize('view', $cours);

        return response()->json([
            'course' => $cours->load('professeurs'),
            'principal' => $cours->professeurPrincipal(),
            'co_professeurs' => $cours->coProfesseurs(),
        ]);
    }

    /**
     * Assigner un ou plusieurs professeurs à un cours.
     *
     * POST /cours/{cours}/professeurs
     * Body: { professeurs: [{ id, role?, date_debut?, date_fin? }, ...] }
     */
    public function assignProfesseursToCours(Request $request, Cours $cours)
    {
        Gate::authorize('update', $cours); // Assuming same policy as course update

        $data = $request->validate([
            'professeurs' => 'required|array',
            'professeurs.*.id' => 'required|exists:professeurs,id',
            'professeurs.*.role' => 'sometimes|in:principal,co-enseignant,remplaçant',
            'professeurs.*.date_debut' => 'sometimes|date',
            'professeurs.*.date_fin' => 'nullable|date',
        ]);

        foreach ($data['professeurs'] as $profData) {
            $profId = $profData['id'];
            $role = $profData['role'] ?? 'co-enseignant';
            $dateDebut = $profData['date_debut'] ?? today();
            $dateFin = $profData['date_fin'] ?? null;

            $cours->professeurs()->syncWithoutDetaching([
                $profId => [
                    'role' => $role,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Professeurs assignés avec succès',
            'course' => $cours->load('professeurs'),
        ]);
    }

    /**
     * Modifier une assignation de professeur à un cours.
     *
     * PUT /cours/{cours}/professeurs/{professeur}
     * Body: { role?, date_debut?, date_fin? }
     */
    public function updateCoursProf(Request $request, Cours $cours, Professeur $professeur)
    {
        Gate::authorize('update', $cours);

        $data = $request->validate([
            'role' => 'sometimes|in:principal,co-enseignant,remplaçant',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'nullable|date',
        ]);

        // Vérifier l'assignation existe
        $exists = $cours->professeursHistorique()
            ->where('professeur_id', $professeur->id)
            ->exists();

        if (!$exists) {
            return response()->json(['error' => 'Assignation non trouvée'], 404);
        }

        $cours->professeurs()->updateExistingPivot($professeur->id, $data);

        return response()->json([
            'message' => 'Assignation mise à jour',
            'course' => $cours->load('professeurs'),
        ]);
    }

    /**
     * Retirer un professeur d'un cours.
     *
     * DELETE /cours/{cours}/professeurs/{professeur}
     */
    public function removeCourseProf(Cours $cours, Professeur $professeur)
    {
        Gate::authorize('delete', $cours);

        // Soft delete via date_fin
        $cours->professeursHistorique()->updateExistingPivot($professeur->id, [
            'date_fin' => today(),
        ]);

        return response()->json([
            'message' => 'Professeur retiré du cours',
            'course' => $cours->load('professeurs'),
        ]);
    }
}
