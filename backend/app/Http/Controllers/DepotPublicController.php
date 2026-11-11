<?php

namespace App\Http\Controllers;

use App\Models\DepotFichier;
use App\Services\DepotPartageService;
use App\Services\DepotService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** DEP-01 T2 : page parent, sans connexion. Lien inconnu, expiré ou révoqué : même réponse 410 (aucune information sur la cause). */
class DepotPublicController extends Controller
{
    private const EN_TETES = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private'];

    public function __construct(
        private readonly DepotPartageService $partages,
        private readonly DepotService $depot,
    ) {}

    public function show(string $token): JsonResponse
    {
        $partage = $this->partages->retrouver($token);
        if ($partage === null) {
            return $this->invalide();
        }

        $partage->forceFill(['nb_ouvertures' => $partage->nb_ouvertures + 1, 'derniere_ouverture_le' => now()])->save();

        return response()->json(['data' => [
            'libelle' => $partage->libelle,
            'expire_le' => $partage->expire_le->toIso8601String(),
            'fichiers' => $partage->fichiers()->orderBy('depot_fichiers.id')->get()
                ->map(fn (DepotFichier $f) => ['id' => $f->id, 'nom' => $f->nom_original, 'taille' => $f->taille])->values(),
        ]], 200, self::EN_TETES);
    }

    public function telecharger(string $token, int $fichier): StreamedResponse|JsonResponse
    {
        $partage = $this->partages->retrouver($token);
        if ($partage === null) {
            return $this->invalide();
        }
        // Le fichier doit faire partie de la sélection de CE partage (id seul : pas de liaison de modèle, donc pas d'indice sur les autres fichiers).
        $fichier = $partage->fichiers()->whereKey($fichier)->first();
        abort_unless($fichier !== null && $this->depot->disque()->exists($fichier->chemin), 404);

        return $this->depot->disque()->download($fichier->chemin, $fichier->nom_original, self::EN_TETES + [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function invalide(): JsonResponse
    {
        return response()->json(['message' => 'Ce lien n\'est plus valable.'], 410, self::EN_TETES);
    }
}
