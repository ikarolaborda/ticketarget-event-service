<?php

declare(strict_types=1);

return [
    // Legacy symmetric secret. Consulted only while accept_hs256 stays on
    // during the RS256 migration overlap.
    'secret' => env('AUTH_JWT_SECRET', ''),

    'issuer' => env('AUTH_JWT_ISSUER', 'ticketarget-users'),

    // RS256 verification: the Users-service JWKS endpoint and its cache TTL.
    'jwks_url' => env('AUTH_JWKS_URL', 'http://users-service:8000/auth/.well-known/jwks.json'),

    'jwks_cache_ttl_seconds' => (int) env('AUTH_JWKS_CACHE_TTL', 3600),

    // Accept legacy HS256 bearers during the RS256 migration window.
    'accept_hs256' => (bool) env('AUTH_JWT_ACCEPT_HS256', true),
];
