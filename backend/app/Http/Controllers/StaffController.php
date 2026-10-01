<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccesCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(private readonly AccesCompteService $acces) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);

        return User::query()
            ->whereIn('role', ['admin', 'directeur'])
            ->when($request->query('statut') === 'actif' || $request->query('statut') === 'inactif',
                fn ($q) => $q->where('statut', $request->query('statut')))
            ->when($request->query('role'),
                fn ($q) => $q->where('role', $request->query('role')))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->payload($u, ['id', 'name', 'email', 'role', 'statut', 'date_sortie']))
            ->values();
    }

    public function show(User $staff)
    {
        Gate::authorize('view', $staff);

        return $this->payload($staff, ['id', 'name', 'email', 'role', 'statut', 'date_sortie', 'created_at']);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,directeur'],
        ]);

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => $this->acces->motDePasseInutilisable(),
            'must_change_password' => false,
            'statut' => 'actif',
        ]);

        $envoi = $this->acces->envoyer($staff->fresh(), AccesCompteService::INVITATION, AccesCompteService::DUREE_ADMIN_MINUTES);

        return response()->json($this->reponseEnvoi($staff->fresh(), $envoi), 201);
    }

    public function update(Request $request, User $staff)
    {
        Gate::authorize('update', $staff);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($staff)],
            'role' => ['sometimes', 'in:admin,directeur'],
        ]);

        if (isset($data['role']) && $data['role'] !== $staff->role) {
            $this->refuserSoi($staff);
            if ($this->isLastAdmin($staff)) {
                abort(409, 'Vous ne pouvez pas retirer le rôle du dernier admin du système.');
            }
        }

        $emailChange = isset($data['email']) && $data['email'] !== $staff->email;

        $staff->update($data);

        // Un changement d'identifiant de connexion invalide les sessions existantes.
        if ($emailChange) {
            $staff->tokens()->delete();
            $this->acces->annulerLiens($staff);
        }

        return response()->json(['data' => $this->payload($staff, ['id', 'name', 'email', 'role', 'statut', 'date_sortie'])]);
    }

    public function destroy(User $staff)
    {
        Gate::authorize('delete', $staff);

        $this->refuserSoi($staff);

        if ($this->aDesdonneesLiees($staff)) {
            abort(409, 'Ce compte a des données liées. Veuillez le désactiver.');
        }

        $staff->delete();

        return response()->noContent();
    }

    public function impactInfo(User $staff)
    {
        Gate::authorize('view', $staff);

        $impact = [
            'isLastAdmin' => $this->isLastAdmin($staff),
            'isLastDirecteur' => $this->isLastDirecteur($staff),
            'timsheetsCount' => $this->countTimesheets($staff),
        ];

        return response()->json(['data' => $impact]);
    }

    public function desactiver(Request $request, User $staff)
    {
        Gate::authorize('update', $staff);

        $this->refuserSoi($staff);

        abort_if($staff->statut === 'inactif', 409, 'Ce compte est déjà désactivé.');

        // Empêcher la désactivation du dernier admin
        if ($this->isLastAdmin($staff)) {
            abort(409, 'Vous ne pouvez pas désactiver le dernier admin du système.');
        }

        $staff->update([
            'statut' => 'inactif',
            'date_sortie' => $request->input('date_sortie') ?? now()->toDateString(),
        ]);

        // Révoquer tous les tokens actifs.
        $staff->tokens()->delete();
        $this->acces->annulerLiens($staff);

        return response()->json(['data' => $this->payload($staff, ['id', 'name', 'email', 'role', 'statut', 'date_sortie'])]);
    }

    public function reactiver(User $staff)
    {
        Gate::authorize('update', $staff);

        abort_if($staff->statut === 'actif', 409, 'Ce compte est déjà actif.');

        // L'ancien mot de passe n'est plus de confiance : le compte repasse par une invitation.
        $staff->update([
            'statut' => 'actif',
            'date_sortie' => null,
            'password' => $this->acces->motDePasseInutilisable(),
            'must_change_password' => false,
            'mot_de_passe_defini_le' => null,
            'invitation_envoyee_le' => null,
        ]);
        $staff->tokens()->delete();

        $envoi = $this->acces->envoyer($staff->fresh(), AccesCompteService::INVITATION, AccesCompteService::DUREE_ADMIN_MINUTES);

        return response()->json($this->reponseEnvoi($staff->fresh(), $envoi));
    }

    /** Renvoie l'invitation (mot de passe jamais défini) ou envoie un lien de réinitialisation. */
    public function envoyerLien(User $staff)
    {
        Gate::authorize('update', $staff);

        $this->exigerCompteActif($staff);

        $attente = $this->acces->attenteEnvoi($staff);
        if ($attente > 0) {
            abort(429, 'Un email vient d\'être envoyé. Réessayez dans '.$attente.' seconde'.($attente > 1 ? 's' : '').'.');
        }

        $envoi = $this->acces->envoyer($staff, $this->acces->typePour($staff), AccesCompteService::DUREE_ADMIN_MINUTES, parDirection: true);

        return response()->json($this->reponseEnvoi($staff->fresh(), $envoi));
    }

    /** Lien à transmettre soi-même (affiché une fois) quand l'email n'arrive pas. */
    public function genererLien(Request $request, User $staff)
    {
        Gate::authorize('update', $staff);

        $this->exigerCompteActif($staff);

        $lien = $this->acces->genererLienManuel($staff, $request->user()->id);

        return response()->json(['data' => $this->payload($staff->fresh(), ['id', 'name', 'email', 'role', 'statut', 'date_sortie']), 'lien' => $lien]);
    }

    private function exigerCompteActif(User $staff): void
    {
        abort_if($staff->statut !== 'actif', 409, 'Réactivez le compte pour envoyer une invitation.');
    }

    /** @param array{envoye: bool, lien: ?string} $envoi */
    private function reponseEnvoi(User $staff, array $envoi): array
    {
        return array_filter([
            'data' => $this->payload($staff, ['id', 'name', 'email', 'role', 'statut', 'date_sortie']),
            'email_envoye' => $envoi['envoye'],
            'lien' => $envoi['lien'],
        ], fn ($v) => $v !== null);
    }

    /** Sérialise via toArray() pour appliquer les casts (date_sortie en Y-m-d). */
    private function payload(User $staff, array $champs): array
    {
        return \Illuminate\Support\Arr::only($staff->toArray(), $champs)
            + ['acces' => $this->acces->resumeAcces($staff)];
    }

    private function refuserSoi(User $staff): void
    {
        abort_if($staff->id === request()->user()->id, 403, 'Vous ne pouvez pas modifier votre propre compte.');
    }

    private function aDesdonneesLiees(User $user): bool
    {
        // Pour l'instant, un directeur sans données liées est rare. On considère qu'il n'a pas d'impact direct.
        // Cas futur : vérifier les classes assignées, etc.
        return false;
    }

    private function isLastAdmin(User $user): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        $count = User::where('role', 'admin')->where('statut', 'actif')->count();
        return $count === 1;
    }

    private function isLastDirecteur(User $user): bool
    {
        if ($user->role !== 'directeur') {
            return false;
        }

        $count = User::where('role', 'directeur')->where('statut', 'actif')->count();
        return $count === 1;
    }

    private function countTimesheets(User $user): int
    {
        // Compter les timesheets en brouillon ou soumis du professeur associé
        if ($user->isProfesseur()) {
            return $user->professeur?->timesheets()
                ->whereIn('statut_validation', ['brouillon', 'soumis'])
                ->count() ?? 0;
        }
        return 0;
    }
}
