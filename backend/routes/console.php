<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CLS-01 T4 : historique des liens conservé 6 mois.
Schedule::command('liens:purge-historique')->dailyAt('03:30');

// SIG-01 : preuves de signature (IP, appareil) conservées 7 ans.
Schedule::command('signatures:anonymiser')->dailyAt('03:00'); // heure pile : cron OVH horaire
