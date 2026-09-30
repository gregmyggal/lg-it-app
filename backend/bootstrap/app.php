<?php

use App\Http\Middleware\EnsureInstallerToken;
use App\Http\Middleware\ValidateShareCode;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Assistant d'installation : hors groupe "web" (ni session ni cookies
        // chiffrés), car il doit tourner avant que .env / APP_KEY / la base existent.
        then: function () {
            Route::middleware(EnsureInstallerToken::class)->group(base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'validate.share.code' => ValidateShareCode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Contrat d'erreurs unifié { message } en français pour l'API (standards §4.5).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (($request->is('api/*') || $request->expectsJson()) && $e->getMessage() === 'This action is unauthorized.') {
                return response()->json(['message' => 'Action non autorisée.'], 403);
            }
        });
    })->create();
