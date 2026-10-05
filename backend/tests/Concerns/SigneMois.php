<?php

namespace Tests\Concerns;

use App\Models\SignatureSpecimen;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/** SIG-01 : signature enregistrée d'un professeur et signature de mois (nécessite Storage::fake('local')). */
trait SigneMois
{
    protected function pngSignature(int $largeur = 300, int $hauteur = 100): string
    {
        $img = imagecreatetruecolor($largeur, $hauteur);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imageline($img, 10, 50, $largeur - 10, 60, imagecolorallocate($img, 26, 61, 143));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    protected function creerSignature(User $user): SignatureSpecimen
    {
        $png = $this->pngSignature();
        Storage::disk('local')->put("signatures/specimens/{$user->id}.png", $png);

        return SignatureSpecimen::create([
            'user_id' => $user->id, 'type' => 'nom', 'texte' => $user->name, 'police' => 'Caveat', 'couleur' => '#1a3d8f',
            'chemin' => "signatures/specimens/{$user->id}.png", 'sha256' => hash('sha256', $png), 'consenti_at' => now(),
        ]);
    }

    protected function signerMois(int $professeurId, int $annee = 2026, int $mois = 10): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/timesheets/sign-month', ['professeur_id' => $professeurId, 'year' => $annee, 'month' => $mois, 'certification' => true]);
    }
}
