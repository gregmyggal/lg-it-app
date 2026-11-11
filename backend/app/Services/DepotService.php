<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\CourseSession;
use App\Models\DepotEspace;
use App\Models\DepotFichier;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** DEP-01 : dépôt de réalisations d'enfants sur un stage (disque privé, liste blanche, quota par espace). */
class DepotService
{
    public function disque(): Filesystem
    {
        return Storage::disk(config('depot.disque'));
    }

    /**
     * Dépose un lot de fichiers ; chaque fichier est contrôlé séparément (les valides sont déposés même si d'autres sont refusés).
     *
     * @param  array<int, UploadedFile>  $fichiers
     * @return array{deposes: array<int, DepotFichier>, refuses: array<int, array{nom: string, message: string}>}
     */
    public function deposer(Classe $classe, ?CourseSession $session, array $fichiers, ?string $libelle, User $auteur): array
    {
        return DB::transaction(function () use ($classe, $session, $fichiers, $libelle, $auteur) {
            // Verrou sur la classe : évite deux espaces « stage entier » créés en parallèle (NULL n'est pas unique en SQL).
            Classe::whereKey($classe->id)->lockForUpdate()->first();
            $espace = DepotEspace::firstOrCreate(['classe_id' => $classe->id, 'course_session_id' => $session?->id]);
            $utilise = (int) $espace->fichiers()->sum('taille');
            $libelle = $libelle !== null && trim($libelle) !== '' ? trim($libelle) : null;

            $deposes = [];
            $refuses = [];
            foreach ($fichiers as $fichier) {
                $nom = $this->nomAffiche($fichier);
                $erreur = $this->controler($fichier, $utilise);
                if ($erreur !== null) {
                    $refuses[] = ['nom' => $nom, 'message' => $erreur];

                    continue;
                }

                $extension = strtolower($fichier->getClientOriginalExtension());
                $taille = (int) $fichier->getSize();
                $chemin = $fichier->storeAs("depots/{$classe->id}", Str::uuid().'.'.$extension, ['disk' => config('depot.disque')]);
                if ($chemin === false) {
                    $refuses[] = ['nom' => $nom, 'message' => 'Le fichier n\'a pas pu être enregistré. Réessayez.'];

                    continue;
                }

                $deposes[] = $espace->fichiers()->create([
                    'nom_original' => $nom,
                    'chemin' => $chemin,
                    'mime' => (string) $fichier->getMimeType(),
                    'taille' => $taille,
                    'libelle' => $libelle,
                    'depose_par' => $auteur->id,
                ]);
                $utilise += $taille;
            }

            return ['deposes' => $deposes, 'refuses' => $refuses];
        });
    }

    public function supprimer(DepotFichier $fichier): void
    {
        $this->disque()->delete($fichier->chemin);
        $fichier->delete();
    }

    private function controler(UploadedFile $fichier, int $utilise): ?string
    {
        $maxMo = config('depot.taille_max_octets') / 1048576;
        if (! $fichier->isValid()) {
            return 'Transfert interrompu ou fichier trop volumineux pour le serveur (max '.round($maxMo).' Mo).';
        }

        $extension = strtolower($fichier->getClientOriginalExtension());
        $types = config('depot.types');
        if (! isset($types[$extension])) {
            return 'Type de fichier non accepté (.'.($extension !== '' ? $extension : '?').').';
        }
        if ($fichier->getSize() > config('depot.taille_max_octets')) {
            return 'Fichier trop volumineux (max '.round($maxMo).' Mo).';
        }
        if (! in_array($fichier->getMimeType(), $types[$extension], true)) {
            return 'Le contenu du fichier ne correspond pas à son extension.';
        }
        if (! $this->signatureValide($fichier, $extension)) {
            return 'Le contenu du fichier ne correspond pas à son extension.';
        }
        if ($utilise + $fichier->getSize() > config('depot.quota_espace_octets')) {
            return 'Espace plein ('.round(config('depot.quota_espace_octets') / 1048576).' Mo). Supprimez des fichiers pour en déposer d\'autres.';
        }

        return null;
    }

    /** Formats sans type MIME fiable (Roblox) : le début du contenu doit correspondre au format annoncé par l'extension. */
    private function signatureValide(UploadedFile $fichier, string $extension): bool
    {
        $regle = config('depot.signatures.'.$extension);
        if ($regle === null) {
            return true;
        }

        $debut = (string) file_get_contents($fichier->getRealPath(), false, null, 0, 512);
        if (isset($regle['debut'])) {
            return str_starts_with($debut, $regle['debut']);
        }

        return str_contains($debut, $regle['contient']);
    }

    /** Nom d'origine nettoyé (sans chemin ni caractère de contrôle), tronqué à 255 caractères. */
    private function nomAffiche(UploadedFile $fichier): string
    {
        $nom = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $fichier->getClientOriginalName()))) ?? '';

        return Str::limit($nom !== '' ? $nom : 'fichier', 255, '');
    }
}
