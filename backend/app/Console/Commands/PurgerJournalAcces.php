<?php

namespace App\Console\Commands;

use App\Services\JournalAccesService;
use Illuminate\Console\Command;

/** RGPD-01 : le journal des accès aux données sensibles est conservé 12 mois. */
class PurgerJournalAcces extends Command
{
    protected $signature = 'acces:purger-journal';

    protected $description = 'Supprime du journal des accès sensibles les traces de plus de 12 mois';

    public function handle(JournalAccesService $journal): int
    {
        $this->info($journal->purger().' trace(s) purgée(s).');

        return self::SUCCESS;
    }
}
