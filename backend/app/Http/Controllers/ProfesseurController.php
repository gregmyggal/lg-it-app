<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyProfesseurRequest;
use App\Http\Resources\ProfesseurResource;
use App\Models\Professeur;
use App\Models\AccesDonneeSensible;
use App\Services\AccesCompteService;
use App\Services\JournalAccesService;
use App\Services\ProfesseurCompteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Rules\Iban;
use Illuminate\Validation\Rule;

class ProfesseurController extends Controller
{
    public function __construct(
        private readonly ProfesseurCompteService $comptes,
        private readonly AccesCompteService $acces,
        private readonly JournalAccesService $journal,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Professeur::class);

        return Professeur::query()
            ->when($request->query('statut') === 'actif' || $request->query('statut') === 'inactif',
                fn ($q) => $q->where('statut', $request->query('statut')))
            ->with(['user:id,name,email,role,statut,must_change_password,invitation_envoyee_le,mot_de_passe_defini_le', 'user.accesTokens'])
            ->withCount(['assignations as classes_count' => fn ($q) => $q->actif()])
            ->orderBy('nom')
            ->get()
            ->map(fn (Professeur $p) => (new ProfesseurResource($this->avecAcces($p)))->resolve());
    }

    public function show(Professeur $professeur)
    {
        Gate::authorize('view', $professeur);

        $professeur->load(['tarifs', 'user:id,name,email,role,statut,must_change_password,invitation_envoyee_le,mot_de_passe_defini_le'])
            ->loadCount(['assignations as classes_count' => fn ($q) => $q->actif()]);

        // IBAN complet : gestionnaires de paie (staff) et titulaire (RGPD-01) ; la lecture est journalisée.
        $this->journal->enregistrer(request()->user(), $professeur, AccesDonneeSensible::LECTURE_IBAN);
        return new ProfesseurResource($this->avecAcces($professeur), avecIban: true);
    }

    // Crée le compte de connexion (User, role=professeur) et le profil ensemble, puis envoie l'invitation
    // (lien à usage unique) à l'email de connexion ; aucun mot de passe n'est communiqué (ADMIN-03).
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
            'envoyer_invitation' => ['sometimes', 'boolean'],
        ]);

        $envoyer = $data['envoyer_invitation'] ?? true;
        unset($data['envoyer_invitation']);

        $r = $this->comptes->creer($data, $envoyer);

        return response()->json($this->reponseEnvoi($r['professeur'], $r['mail_envoye'], $r['lien']), 201);
    }

    public function update(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'prenom' => ['sometimes', 'string', 'max:255'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'telephone' => ['nullable', 'string', 'max:255'],
            'compte_bancaire' => ['nullable', 'string', 'max:50', new Iban],
            'date_entree' => ['sometimes', 'date'],
            'type_contrat' => ['nullable', 'in:salarie,freelance,prestataire'],
            'photo_path' => ['nullable', 'string'],
        ]);

        if (array_key_exists('compte_bancaire', $data)) {
            $data['compte_bancaire'] = Iban::normaliser($data['compte_bancaire']);
        }

        $professeur->update($data);
        $this->journal->enregistrer($request->user(), $professeur, AccesDonneeSensible::LECTURE_IBAN);

        return new ProfesseurResource($professeur, avecIban: true);
    }

    // PROF-02 : sans heure encodée, suppression directe ; sinon 409 avec résumé, puis forçage (motif + nom).
    public function destroy(DestroyProfesseurRequest $request, Professeur $professeur)
    {
        $this->refuserSoi($professeur);

        $this->comptes->supprimer($professeur, $request->user(), $request->forcer() ? $request->validated('motif') : null);

        return response()->noContent();
    }

    public function impact(Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        return response()->json(['data' => $this->comptes->impact($professeur)]);
    }

    public function impactSuppression(Request $request, Professeur $professeur)
    {
        Gate::authorize('delete', $professeur);

        return response()->json(['data' => $this->comptes->resumeSuppression($professeur, $request->user())]);
    }

    public function desactiver(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);
        $this->refuserSoi($professeur);

        $data = $request->validate(['date_sortie' => ['nullable', 'date']]);

        $r = $this->comptes->desactiver($professeur, $data['date_sortie'] ?? null);

        return response()->json([
            'data' => $r['professeur'],
            'assignations_terminees' => $r['assignations_terminees'],
            'seances_liberees' => $r['seances_liberees'],
        ]);
    }

    public function reactiver(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $options = $request->validate(['envoyer_invitation' => ['sometimes', 'boolean']]);

        $r = $this->comptes->reactiver($professeur, $options['envoyer_invitation'] ?? true);

        return response()->json($this->reponseEnvoi($r['professeur'], $r['mail_envoye'], $r['lien']));
    }

    public function envoyerLien(Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $r = $this->comptes->envoyerLien($professeur);

        return response()->json($this->reponseEnvoi($professeur->refresh(), $r['mail_envoye'], $r['lien']));
    }

    // ADMIN-05 : envoi en lot des invitations « accès non envoyé » (plafond AccesCompteService::LOT_MAX).
    public function envoyerInvitations(Request $request)
    {
        Gate::authorize('create', Professeur::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.AccesCompteService::LOT_MAX],
            'ids.*' => ['integer', 'distinct', 'exists:professeurs,id'],
        ]);

        $professeurs = Professeur::with('user')->whereIn('id', $data['ids'])->get();
        $professeurs->each(fn (Professeur $p) => Gate::authorize('update', $p));

        $users = $professeurs->map(function (Professeur $p) {
            $p->user->setRelation('professeur', $p);

            return $p->user;
        });

        return response()->json(['data' => $this->acces->envoyerEnLot($users)]);
    }

    public function genererLien(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $lien = $this->comptes->genererLien($professeur, $request->user()->id);

        return response()->json(['data' => $this->avecAcces($professeur->refresh()), 'lien' => $lien]);
    }

    public function changerEmailConnexion(Request $request, Professeur $professeur)
    {
        Gate::authorize('update', $professeur);

        $data = $request->validate([
            'login_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($professeur->user_id)],
        ]);

        return response()->json(['data' => $this->comptes->changerEmailConnexion($professeur, $data['login_email'])]);
    }

    /** Ajoute le résumé d'accès (calculé par le backend) au professeur sérialisé. */
    private function avecAcces(Professeur $professeur): Professeur
    {
        $professeur->loadMissing('user');
        $professeur->user->setRelation('professeur', $professeur);
        $professeur->setAttribute('acces', $this->acces->resumeAcces($professeur->user));

        return $professeur;
    }

    private function reponseEnvoi(Professeur $professeur, bool $envoye, ?string $lien): array
    {
        return array_filter([
            'data' => $this->avecAcces($professeur->load('user')),
            'mail_envoye' => $envoye,
            'lien' => $lien,
        ], fn ($v) => $v !== null);
    }

    private function refuserSoi(Professeur $professeur): void
    {
        abort_if($professeur->user_id === request()->user()->id, 403, 'Action non autorisée.');
    }
}
