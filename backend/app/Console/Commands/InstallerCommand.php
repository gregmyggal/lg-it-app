<?php

namespace App\Console\Commands;

use App\Support\Installer\InstallToken;
use Illuminate\Console\Command;

class InstallerCommand extends Command
{
    protected $signature = 'app:installer
        {--url= : URL publique du site (défaut : APP_URL)}
        {--ttl=24 : Durée de validité du lien, en heures}
        {--revoke : Désactive immédiatement l\'assistant}';

    protected $description = "Ouvre l'assistant web d'installation/configuration (/install) avec un lien à usage unique";

    public function handle(): int
    {
        if ($this->option('revoke')) {
            InstallToken::revoke();
            $this->info('Assistant /install désactivé.');

            return self::SUCCESS;
        }

        $ttl = max(1, (int) $this->option('ttl'));
        $url = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $token = InstallToken::issue($ttl);

        $this->line("Assistant d'installation ouvert pour {$ttl} h (un seul lien valide à la fois) :");
        $this->newLine();
        $this->line("  {$url}/install?token={$token}");
        $this->newLine();
        $this->line("Le lien est désactivé automatiquement à la fin de l'assistant (ou : php artisan app:installer --revoke).");

        return self::SUCCESS;
    }
}
