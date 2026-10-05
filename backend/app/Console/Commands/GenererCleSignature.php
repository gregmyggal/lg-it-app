<?php

namespace App\Console\Commands;

use App\Services\SignatureCle;
use Illuminate\Console\Command;

/** SIG-01 : génère une paire de clés Ed25519 pour sceller les signatures (à placer dans le .env de production). */
class GenererCleSignature extends Command
{
    protected $signature = 'signature:cle {--id= : Identifiant de la clé (ex. cle-2026)}';

    protected $description = 'Génère la clé Ed25519 qui scelle les signatures des fiches de défraiement';

    public function handle(): int
    {
        $cle = SignatureCle::generer();
        $id = $this->option('id') ?: 'cle-'.now()->format('Ymd');

        $this->line('Ajoutez au .env (et conservez une sauvegarde hors serveur) :');
        $this->newLine();
        $this->line("SIGNATURE_CLE_ID={$id}");
        $this->line("SIGNATURE_CLE_PRIVEE={$cle['privee']}");
        $this->newLine();
        $this->line("Clé publique ({$id}) : {$cle['publique']}");
        $this->line('Lors d\'une rotation, ajoutez l\'ancienne clé publique à SIGNATURE_CLES_PUBLIQUES (JSON) pour vérifier les anciennes signatures.');

        return self::SUCCESS;
    }
}
