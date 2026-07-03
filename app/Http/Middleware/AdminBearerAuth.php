<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards catalog administration with dual acceptance: a platform JWT carrying
 * the is_admin claim (humans in the browser) OR a Sanctum personal access
 * token with the events:write ability (CLI and service callers).
 *
 * Precedence contract: a JWT-shaped bearer (three dot-separated segments)
 * that fails verification is rejected outright — it never falls through to
 * Sanctum, so a forged JWT can't be retried as a personal access token.
 */
final readonly class AdminBearerAuth
{
    public function __construct(private AuthTokenVerifier $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if (count(explode('.', $bearer)) === 3) {
            $claims = $this->tokens->verify($bearer);

            if ($claims === null) {
                return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
            }

            if ($claims['is_admin'] !== true) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->tokenCan('events:write')) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
