<?php

namespace App\Console\Commands;

use App\Services\TimesheetVersionService;
use Illuminate\Console\Command;

/** TS-04 : le détail des versions soumise/validée est conservé 24 mois après la fin du mois (totaux et empreinte conservés). */
class PurgerVersionsTimesheet extends Command
{
    protected $signature = 'timesheets:purger-versions {--simulation : compte les versions concernées sans rien effacer}';

    protected $description = 'Efface le détail des versions de timesheet de plus de 24 mois (totaux et empreinte conservés)';

    public function handle(TimesheetVersionService $versions): int
    {
        $n = $versions->purger(simulation: (bool) $this->option('simulation'));
        $this->info($this->option('simulation') ? "{$n} version(s) seraient purgée(s)." : "{$n} version(s) purgée(s).");

        return self::SUCCESS;
    }
}
