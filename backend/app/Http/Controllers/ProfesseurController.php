<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProfesseurController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Professeur::class);

        return Professeur::with('typesCours')->orderBy('nom')->get();
    }

    public function show(Professeur $professeur)
    {
        Gate::authorize('view', $professeur);

        return $professeur->load('typesCours');
    }

    // Crée le compte de connexion (User, role=professeur) et le profil (Professeur) ensemble.
    public function store(Request $request)
    {
        Gate::authorize('create', Professeur::class);

        $data = $request->validate([
            'login_email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'telephone' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'in:actif,inactif'],
            'date_entree' => ['required', 'date'],
            'date_sortie' => ['nullable', 'date'],
            'type_contrat' => ['nullable', 'in:salarie,freelance,prestataire'],
            'photo_path' => ['nullable', 'string'],
            'types_cours' => ['nullable', 'array'],
            'types_cours.*' => ['integer', 'exists:types_cours,id'],
        ]);

        $professeur = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['prenom'].' '.$data['nom'],
                'email' => $data['login_email'],
                'password' => $data['password'],
                'role' => 'professeur',
            ]);

            $professeur = Professeur::create([
                ...array_diff_key($data, array_flip(['login_email', 'password', 'types_cours'])),
                'user_id' => $user->id,
            ]);

            $professeur->typesCours()->sync($data['types_cours'] ?? []);

            return $professeur;
        });

        return response()->json($professeur->load('typesCours', 'user'), 201);
    }

    public function update(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'prenom' => ['sometimes', 'string', 'max:255'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'telephone' => ['nullable', 'string', 'max:255'],
            'statut' => ['sometimes', 'in:actif,inactif'],
            'date_entree' => ['sometimes', 'date'],
            'date_sortie' => ['nullable', 'date'],
            'type_contrat' => ['nullable', 'in:salarie,freelance,prestataire'],
            'photo_path' => ['nullable', 'string'],
            'types_cours' => ['nullable', 'array'],
            'types_cours.*' => ['integer', 'exists:types_cours,id'],
        ]);

        $professeur->update(array_diff_key($data, array_flip(['types_cours'])));

        if (array_key_exists('types_cours', $data)) {
            $professeur->typesCours()->sync($data['types_cours']);
        }

        return $professeur->load('typesCours');
    }

    // Supprime aussi le compte de connexion lié (cascade FK professeurs.user_id).
    public function destroy(Professeur $professeur)
    {
        Gate::authorize('delete', $professeur);

        $professeur->user->delete();

        return response()->noContent();
    }
}
