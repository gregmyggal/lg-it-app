<?php

namespace App\Http\Middleware;

use App\Support\Installer\InstallToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /install n'existe que pour qui détient le jeton émis en SSH : sinon 404,
 * pour ne rien révéler de l'assistant sur un site en ligne.
 */
class EnsureInstallerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Install-Token') ?? $request->query('token');

        abort_unless(InstallToken::isValid(is_string($token) ? $token : null), 404);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
