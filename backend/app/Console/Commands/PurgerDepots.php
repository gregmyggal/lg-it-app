<?php

namespace App\Console\Commands;

use App\Services\DepotPurgeService;
use Illuminate\Console\Command;

/** DEP-01 : suppression des fichiers déposés 60 jours après l'expiration de leur dernier lien. */
class PurgerDepots extends Command
{
    protected $signature = 'depots:purger {--simulation : compte les éléments concernés sans rien supprimer}';

    protected $description = 'Supprime les fichiers de dépôt de plus de 60 jours après expiration, et efface les copies chiffrées des tokens échus';

    public function handle(DepotPurgeService $purge): int
    {
        $n = $purge->purger(simulation: (bool) $this->option('simulation'));
        $verbe = $this->option('simulation') ? 'seraient supprimé(s)' : 'supprimé(s)';
        $this->info("{$n['fichiers']} fichier(s) {$verbe}, {$n['partages']} lien(s) vide(s) {$verbe}, {$n['tokens']} token(s) échu(s) effacé(s).");

        return self::SUCCESS;
    }
}
