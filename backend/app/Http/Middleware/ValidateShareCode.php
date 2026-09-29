<?php

namespace App\Http\Middleware;

use App\Models\ShareCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateShareCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->header('X-Share-Code') ?? $request->query('code');

        if (!$code) {
            return response()->json(['error' => 'Share code is required'], 401);
        }

        $shareCode = ShareCode::where('code', $code)->first();

        if (!$shareCode) {
            return response()->json(['error' => 'Invalid share code'], 404);
        }

        if (!$shareCode->isValid()) {
            return response()->json(['error' => 'Share code is expired or has reached its usage limit'], 403);
        }

        $shareCode->incrementUsage();

        $request->attributes->add(['shareCode' => $shareCode]);
        $request->attributes->add(['shareable' => $shareCode->shareable]);

        return $next($request);
    }
}
