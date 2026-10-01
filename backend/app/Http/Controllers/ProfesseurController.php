<?php

namespace App\Http\Controllers;

use App\Models\Professeur;
use App\Services\ProfesseurCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfesseurController extends Controller
{
    public function __construct(private readonly ProfesseurCompteService $comptes) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Professeur::class);

        return Professeur::query()
            ->when($request->query('statut') === 'actif' || $request->query('statut') === 'inactif',
                fn ($q) => $q->where('statut', $request->query('statut')))
            ->with('user:id,email,must_change_password')
            ->withCount(['assignations as classes_count' => fn ($q) => $q->actif()])
            ->orderBy('nom')
            ->get();
    }

    public function show(Professeur $professeur)
    {
        Gate::authorize('view', $professeur);

        return $professeur->load(['tarifs', 'user:id,email'])
            ->loadCount(['assignations as classes_count' => fn ($q) => $q->actif()]);
    }

    // Crée le compte de connexion (User, role=professeur) et le profil ensemble ; le mot de passe provisoire
    // est généré par le serveur, envoyé par mail et renvoyé une seule fois (PROF-01).
    public function store(Request $request)
    {
        Gate::authorize('create', Professeur::class);

        $data = $request->validate([
            'login_email' => ['required', 'email', 'unique:users,email'],
            'prenom' => ['required', 'string', 'max:255'],
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'telephone' => ['nullable', 'string', 'max:255'],
            'date_entree' => ['required', 'date'],
            'type_contrat' => ['nullable', 'in:salarie,freelance,prestataire'],
            'photo_path' => ['nullable', 'string'],
        ]);

        $r = $this->comptes->creer($data);

        return response()->json(['data' => $r['professeur'], 'mot_de_passe' => $r['mot_de_passe'], 'mail_envoye' => $r['mail_envoye']], 201);
    }

    public function update(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'prenom' => ['sometimes', 'string', 'max:255'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'telephone' => ['nullable', 'string', 'max:255'],
            'date_entree' => ['sometimes', 'date'],
            'type_contrat' => ['nullable', 'in:salarie,freelance,prestataire'],
            'photo_path' => ['nullable', 'string'],
        ]);

        $professeur->update($data);

        return $professeur;
    }

    // Suppression réservée à un professeur sans donnée liée (sinon 409 : désactiver).
    public function destroy(Professeur $professeur)
    {
        Gate::authorize('delete', $professeur);
        $this->refuserSoi($professeur);

        $this->comptes->supprimer($professeur);

        return response()->noContent();
    }

    public function impact(Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        return response()->json(['data' => $this->comptes->impact($professeur)]);
    }

    public function desactiver(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);
        $this->refuserSoi($professeur);

        $data = $request->validate([
            'terminer_assignations' => ['sometimes', 'boolean'],
            'date_sortie' => ['nullable', 'date'],
        ]);

        $r = $this->comptes->desactiver($professeur, $data['terminer_assignations'] ?? true, $data['date_sortie'] ?? null);

        return response()->json(['data' => $r['professeur'], 'assignations_terminees' => $r['assignations_terminees']]);
    }

    public function reactiver(Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $r = $this->comptes->reactiver($professeur);

        return response()->json(['data' => $r['professeur'], 'mot_de_passe' => $r['mot_de_passe'], 'mail_envoye' => $r['mail_envoye']]);
    }

    public function reinitialiserMotDePasse(Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        return response()->json($this->comptes->reinitialiserMotDePasse($professeur));
    }

    public function changerEmailConnexion(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'login_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($professeur->user_id)],
        ]);

        return response()->json(['data' => $this->comptes->changerEmailConnexion($professeur, $data['login_email'])]);
    }

    private function refuserSoi(Professeur $professeur): void
    {
        abort_if($professeur->user_id === request()->user()->id, 403, 'Action non autorisée.');
    }
}
