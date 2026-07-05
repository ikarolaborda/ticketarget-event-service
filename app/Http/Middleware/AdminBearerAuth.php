<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards catalog administration. Since the Users service became the sole
 * token issuer (RS256), this accepts exactly one credential: a platform JWT
 * carrying is_admin. CLI and service callers mint one with the Users service's
 * `auth:issue-token` command — the old Sanctum personal-access-token path (and
 * its cross-context read of the users table) is gone.
 */
final readonly class AdminBearerAuth
{
    public function __construct(private AuthTokenVerifier $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();

        if ($bearer === '') {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $claims = $this->tokens->verify($bearer);

        if ($claims === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($claims['is_admin'] !== true) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
