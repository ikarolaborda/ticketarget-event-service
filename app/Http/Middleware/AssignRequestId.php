<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Ticketarget\Logging\CorrelationContext;

/**
 * Adopts the gateway-provided X-Request-Id (or mints one) as the correlation id
 * for this request so all log lines and the response share a single trace id.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: bin2hex(random_bytes(16));
        CorrelationContext::set($requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
