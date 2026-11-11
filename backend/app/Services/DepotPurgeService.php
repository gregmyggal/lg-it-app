<?php

namespace App\Services;

use App\Models\DepotEspace;
use App\Models\DepotFichier;
use App\Models\DepotPartage;

/**
 * DEP-01 (RG-6) : un fichier est supprimé 60 jours après l'expiration (ou la révocation) de son dernier lien,
 * ou 60 jours après son dépôt s'il n'a jamais été partagé. Les copies chiffrées de token des liens échus sont effacées.
 */
class DepotPurgeService
{
    public const RETENTION_JOURS = 60;

    public function __construct(private readonly DepotService $depot) {}

    /** @return array{fichiers: int, partages: int, tokens: int} */
    public function purger(bool $simulation = false): array
    {
        $limite = now()->subDays(self::RETENTION_JOURS);
        $fichiers = 0;

        DepotFichier::where('created_at', '<', $limite)->with('partages')->chunkById(100, function ($lot) use ($limite, $simulation, &$fichiers) {
            foreach ($lot as $fichier) {
                $fin = $fichier->partages->map->finEffective()->max() ?? $fichier->created_at;
                if ($fin->lt($limite)) {
                    $fichiers++;
                    if (! $simulation) {
                        $this->depot->supprimer($fichier);
                    }
                }
            }
        });

        // Liens échus depuis plus de 60 jours et vidés de leurs fichiers : on supprime aussi leurs statistiques et leur journal d'envoi.
        $partagesVides = DepotPartage::where('expire_le', '<', $limite)->whereDoesntHave('fichiers');
        $partages = $simulation ? $partagesVides->count() : $partagesVides->delete();

        // Copie chiffrée du token : inutile dès que le lien est échu.
        $tokensEchus = DepotPartage::whereNotNull('token_chiffre')->where('expire_le', '<', now());
        $tokens = $simulation ? $tokensEchus->count() : $tokensEchus->update(['token_chiffre' => null]);

        if (! $simulation) {
            DepotEspace::whereDoesntHave('fichiers')->delete();
        }

        return ['fichiers' => $fichiers, 'partages' => $partages, 'tokens' => $tokens];
    }
}
