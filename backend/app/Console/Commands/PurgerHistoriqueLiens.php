<?php

namespace App\Console\Commands;

use App\Models\ClasseLien;
use App\Models\ClasseLienVersion;
use Illuminate\Console\Command;

/**
 * CLS-01 T4 (R-T4-8) : l'historique des liens est conservé 6 mois. Supprime les versions plus anciennes et les liens
 * archivés depuis plus de 6 mois (ils ne sont plus restaurables). Planifiée chaque nuit.
 */
class PurgerHistoriqueLiens extends Command
{
    protected $signature = 'liens:purge-historique';

    protected $description = 'Purge l\'historique des liens de cours de plus de 6 mois et les liens archivés depuis plus de 6 mois';

    public function handle(): int
    {
        $limite = now()->subMonths(6);

        $versions = ClasseLienVersion::where('created_at', '<', $limite)->delete();
        $liens = ClasseLien::onlyTrashed()->where('deleted_at', '<', $limite)->forceDelete();

        $this->info("{$versions} version(s) et {$liens} lien(s) archivé(s) purgés.");

        return self::SUCCESS;
    }
}
