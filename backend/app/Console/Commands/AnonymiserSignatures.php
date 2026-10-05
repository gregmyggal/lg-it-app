<?php

namespace App\Console\Commands;

use App\Services\SignatureNumeriqueService;
use Illuminate\Console\Command;

/** SIG-01 (RGPD) : efface l'IP, l'appareil et le contenu détaillé des signatures de plus de 7 ans. */
class AnonymiserSignatures extends Command
{
    protected $signature = 'signatures:anonymiser';

    protected $description = 'Anonymise les preuves de signature au-delà de la durée de conservation';

    public function handle(SignatureNumeriqueService $signatures): int
    {
        $this->info($signatures->anonymiser().' signature(s) anonymisée(s).');

        return self::SUCCESS;
    }
}
