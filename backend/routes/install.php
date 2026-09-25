<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Assistant post-déploiement — accessible uniquement avec le jeton émis par
// `php artisan app:installer` (cf. EnsureInstallerToken), 404 sinon.
Route::prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'show']);
    Route::get('/status', [InstallController::class, 'status']);
    Route::post('/test/database', [InstallController::class, 'testDatabase']);
    Route::post('/test/mail', [InstallController::class, 'testMail']);
    Route::post('/run/{step}', [InstallController::class, 'run'])->whereIn('step', ['env', 'migrate', 'admin', 'optimize', 'finish']);
});
