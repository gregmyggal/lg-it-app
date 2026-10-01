<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCoursLienRequest;
use App\Http\Resources\ClasseLienResource;
use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use App\Models\Cours;
use App\Services\CoursLienService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Liens d'un cours (CLS-01 T4) : liste, création, ordre, historique, reprise des anciennes ressources. */
class CoursLienController extends Controller
{
    public function __construct(private readonly CoursLienService $service) {}

    public function index(Request $request, Cours $cours): JsonResponse
    {
        Gate::authorize('viewAny', [ClasseLien::class, $cours]);
        $user = $request->user();

        $classes = $cours->classes()->where('statut', 'active')->with(['anneeScolaire:id,libelle', 'prochaineSession'])
            ->orderBy('annee_scolaire_id')->orderBy('periode_id')->orderBy('jour_semaine')->orderBy('heure_debut')->get();

        return response()->json([
            'cours' => ['id' => $cours->id, 'titre' => $cours->titre],
            // Classes actives du cours : portée affichée dans le bandeau et « séance courante » de chacune.
            'classes' => $classes->map(fn ($c) => [
                'id' => $c->id,
                'jour_semaine' => $c->jour_semaine,
                'heure_debut' => substr($c->heure_debut, 0, 5),
                'heure_fin' => substr($c->heure_fin, 0, 5),
                'annee_scolaire' => $c->anneeScolaire?->libelle,
                'seance_courante' => $c->prochaineSession ? ['seance_numero' => $c->prochaineSession->seance_numero, 'bis' => $c->prochaineSession->bis_rang > 0, 'date' => $c->prochaineSession->date->toDateString()] : null,
            ])->values(),
            'data' => ClasseLienResource::collection($this->service->liste($cours))->resolve($request),
            'peut_modifier' => $user->can('create', [ClasseLien::class, $cours]),
            'peut_voir_historique' => $user->can('viewHistory', [ClasseLien::class, $cours]),
            'anciennes_ressources' => $this->service->ressourcesNonReprises($cours),
        ]);
    }

    public function store(StoreCoursLienRequest $request, Cours $cours): JsonResponse
    {
        Gate::authorize('create', [ClasseLien::class, $cours]);

        $lien = $this->service->creer($cours, $request->user(), $request->validated());

        return (new ClasseLienResource($lien))->response()->setStatusCode(201);
    }

    public function ordre(Request $request, Cours $cours): JsonResponse
    {
        Gate::authorize('create', [ClasseLien::class, $cours]);

        $v = $request->validate([
            'seance_numero' => ['present', 'nullable', 'integer', 'min:1'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $liste = $this->service->ordonner($cours, $request->user(), $v['seance_numero'], $v['ids']);

        return response()->json(['data' => ClasseLienResource::collection($liste)->resolve($request)]);
    }

    /** Versions des 6 derniers mois (filtres : portée, auteur, action). */
    public function historique(Request $request, Cours $cours): JsonResponse
    {
        Gate::authorize('viewHistory', [ClasseLien::class, $cours]);

        $f = $request->validate([
            'portee' => ['sometimes', 'string'], // « generaux » ou n° de séance
            'auteur' => ['sometimes', 'integer'],
            'action' => ['sometimes', 'in:'.implode(',', ClasseLienVersion::ACTIONS)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $peutRestaurer = $request->user()->can('create', [ClasseLien::class, $cours]);

        $query = ClasseLienVersion::query()
            ->where('parent_type', ClasseLien::PARENT_COURS)->where('parent_id', $cours->id)
            ->where('created_at', '>=', now()->subMonths(6))
            ->with('auteur:id,name')
            ->when(isset($f['auteur']), fn ($q) => $q->where('user_id', $f['auteur']))
            ->when(isset($f['action']), fn ($q) => $q->where('action', $f['action']))
            ->latest('id');

        $page = $query->paginate((int) ($f['per_page'] ?? 50));
        $titres = ClasseLien::withTrashed()->whereIn('id', $page->pluck('classe_lien_id'))->pluck('titre', 'id');

        $versions = $page->getCollection()
            ->filter(fn (ClasseLienVersion $v) => ! isset($f['portee']) || $this->dansPortee($v, $f['portee']))
            ->map(fn (ClasseLienVersion $v) => [
                'id' => $v->id,
                'lien_id' => $v->classe_lien_id,
                // Titre du lien à l'époque de la version (sinon le titre actuel, pour un réordonnancement).
                'lien_titre' => $v->apres['titre'] ?? $v->avant['titre'] ?? $titres[$v->classe_lien_id] ?? null,
                'action' => $v->action,
                'avant' => $v->avant,
                'apres' => $v->apres,
                'auteur' => $v->auteur ? ['id' => $v->auteur->id, 'nom' => $v->auteur->name] : null,
                'created_at' => $v->created_at->toIso8601String(),
                'restaure_depuis_id' => $v->restaure_depuis_id,
                'peut_restaurer' => $peutRestaurer && $v->action !== ClasseLienVersion::ACTION_CREATION,
                'expiree' => $v->estExpiree(),
            ])->values();

        return response()->json([
            'data' => $versions,
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function reprendre(Request $request, Cours $cours): JsonResponse
    {
        Gate::authorize('create', [ClasseLien::class, $cours]);

        return response()->json(['creees' => $this->service->reprendreRessources($cours, $request->user())]);
    }

    /** Une version appartient à une portée si son état avant/après y est (généraux ou séance n). */
    private function dansPortee(ClasseLienVersion $v, string $portee): bool
    {
        $cible = $portee === 'generaux' ? null : (int) $portee;
        foreach ([$v->avant, $v->apres] as $etat) {
            if (is_array($etat) && array_key_exists('liste', $etat) ? ($etat['seance_numero'] ?? null) === $cible
                : (is_array($etat) && array_key_exists('seance_numero', $etat) && $etat['seance_numero'] === $cible)) {
                return true;
            }
        }

        return false;
    }
}
