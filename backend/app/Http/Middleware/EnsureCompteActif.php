<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** PROF-01 + ADMIN-01 : un compte désactivé (prof, admin, directeur) n'accède plus à rien. */
class EnsureCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $inactif = false;

        if ($user->isProfesseur() && $user->professeur?->statut === 'inactif') {
            $inactif = true;
        }

        if (($user->isAdmin() || $user->isDirecteur()) && $user->statut === 'inactif') {
            $inactif = true;
        }

        if ($inactif) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Ce compte est désactivé. Contactez l\'administrateur.', 'code' => 'compte_desactive'], 403);
        }

        return $next($request);
    }
}
