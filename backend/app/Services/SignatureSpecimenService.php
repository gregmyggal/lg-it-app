<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\SignatureSpecimen;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * SIG-01 : enregistre la signature (image) d'un professeur. Le PNG reçu est contrôlé (type réel, poids, dimensions)
 * puis ré-encodé avec GD : seule l'image est conservée, jamais le fichier d'origine.
 */
class SignatureSpecimenService
{
    public const POIDS_MAX = 300 * 1024;

    public const LARGEUR_MAX = 2000;

    public const HAUTEUR_MAX = 800;

    /** @param array{type: string, texte?: ?string, police?: ?string, couleur: string, image: string} $v */
    public function enregistrer(User $user, array $v): SignatureSpecimen
    {
        $png = $this->png($v['image']);
        $sha = hash('sha256', $png);
        $chemin = sprintf('signatures/specimens/%d-%s.png', $user->id, substr($sha, 0, 16));
        Storage::disk('local')->put($chemin, $png);

        $ancien = SignatureSpecimen::where('user_id', $user->id)->first();
        $specimen = SignatureSpecimen::updateOrCreate(['user_id' => $user->id], [
            'type' => $v['type'],
            'texte' => $v['type'] === 'dessin' ? null : ($v['texte'] ?? null),
            'police' => $v['type'] === 'dessin' ? null : ($v['police'] ?? null),
            'couleur' => $v['couleur'],
            'chemin' => $chemin,
            'sha256' => $sha,
            'consenti_at' => now(),
        ]);
        // Les signatures de mois gardent leur propre copie : l'ancienne image du profil peut disparaître.
        if ($ancien && $ancien->chemin !== $chemin) {
            Storage::disk('local')->delete($ancien->chemin);
        }

        return $specimen;
    }

    public function dataUrl(SignatureSpecimen|string $source): ?string
    {
        $chemin = $source instanceof SignatureSpecimen ? $source->chemin : $source;
        $disque = Storage::disk('local');

        return $disque->exists($chemin) ? 'data:image/png;base64,'.base64_encode($disque->get($chemin)) : null;
    }

    private function png(string $dataUrl): string
    {
        $invalide = fn (string $m) => RegleMetierException::invalide($m, ['image' => [$m]]);
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw $invalide('La signature doit être une image PNG.');
        }
        $brut = base64_decode(substr($dataUrl, 22), true);
        if ($brut === false || $brut === '') {
            throw $invalide('Image de signature illisible.');
        }
        if (strlen($brut) > self::POIDS_MAX) {
            throw $invalide('Image de signature trop lourde (300 Ko maximum).');
        }
        $info = @getimagesizefromstring($brut);
        if (! $info || $info[2] !== IMAGETYPE_PNG) {
            throw $invalide('La signature doit être une image PNG.');
        }
        if ($info[0] > self::LARGEUR_MAX || $info[1] > self::HAUTEUR_MAX || $info[0] < 20 || $info[1] < 10) {
            throw $invalide('Dimensions de la signature invalides.');
        }

        $image = @imagecreatefromstring($brut);
        if ($image === false) {
            throw $invalide('Image de signature illisible.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
