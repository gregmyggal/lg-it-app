<?php

namespace App\Console\Commands;

use App\Support\ChiffrementIban;
use Illuminate\Console\Command;

/** RGPD-01 : chiffre les IBAN restés en clair (idempotent ; exécuté aussi par la migration). */
class ChiffrerIbanProfesseurs extends Command
{
    protected $signature = 'professeurs:chiffrer-iban {--dry-run : Compte sans rien modifier}';

    protected $description = 'Chiffre les IBAN des professeurs encore en clair (idempotent)';

    public function handle(): int
    {
        $r = ChiffrementIban::chiffrerTout((bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%s : %d IBAN %s, %d déjà chiffré(s), %d vide(s).',
            $this->option('dry-run') ? 'Simulation' : 'Terminé',
            $r['chiffres'],
            $this->option('dry-run') ? 'à chiffrer' : 'chiffré(s)',
            $r['deja_chiffres'],
            $r['vides'],
        ));

        return self::SUCCESS;
    }
}
