<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
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
            ->get(['id', 'name', 'email', 'role', 'statut', 'date_sortie']);
    }

    public function show(User $staff)
    {
        Gate::authorize('view', $staff);

        return $staff->only(['id', 'name', 'email', 'role', 'statut', 'date_sortie', 'created_at']);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,directeur'],
        ]);

        $password = Str::random(12);

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => bcrypt($password),
            'must_change_password' => true,
            'statut' => 'actif',
        ]);

        return response()->json(['data' => $staff, 'password' => $password], 201);
    }

    public function update(Request $request, User $staff)
    {
        Gate::authorize('update', $staff);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($staff)],
            'role' => ['sometimes', 'in:admin,directeur'],
        ]);

        $staff->update($data);

        return response()->json(['data' => $staff->only(['id', 'name', 'email', 'role', 'statut', 'date_sortie'])]);
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

        return response()->json(['data' => $staff->only(['id', 'name', 'email', 'role', 'statut', 'date_sortie'])]);
    }

    public function reactiver(User $staff)
    {
        Gate::authorize('update', $staff);

        $password = Str::random(12);

        $staff->update([
            'statut' => 'actif',
            'date_sortie' => null,
            'password' => bcrypt($password),
            'must_change_password' => true,
        ]);

        return response()->json(['data' => $staff->only(['id', 'name', 'email', 'role', 'statut']), 'password' => $password]);
    }

    public function reinitialiserMotDePasse(User $staff)
    {
        Gate::authorize('update', $staff);

        $password = Str::random(12);

        $staff->update([
            'password' => bcrypt($password),
            'must_change_password' => true,
        ]);

        // Révoquer les tokens existants.
        $staff->tokens()->delete();

        return response()->json(['password' => $password, 'message' => 'Mot de passe réinitialisé']);
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
