<?php

namespace App\Console\Commands;

use App\Services\TimesheetNoteService;
use Illuminate\Console\Command;

/** TS-06 : les notes internes (et leur historique) sont conservées 24 mois après la fin du mois concerné. */
class PurgerNotesTimesheet extends Command
{
    protected $signature = 'timesheets:purger-notes {--simulation : compte les notes concernées sans rien supprimer}';

    protected $description = 'Supprime les notes internes de timesheet de plus de 24 mois (historique compris)';

    public function handle(TimesheetNoteService $notes): int
    {
        $n = $notes->purger(simulation: (bool) $this->option('simulation'));
        $this->info($this->option('simulation') ? "{$n} note(s) seraient supprimée(s)." : "{$n} note(s) supprimée(s).");

        return self::SUCCESS;
    }
}
