<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CLS-01 T4 : historique des liens conservé 6 mois.
Schedule::command('liens:purge-historique')->dailyAt('03:30');
