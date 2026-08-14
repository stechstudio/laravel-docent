<?php

declare(strict_types=1);

namespace STS\Docent\Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in for whatever a host puts after its guard — security headers,
 * response shaping, tenancy. A share response has to go through it like any
 * other response, so this stamps one it can be recognised by.
 */
final class StampsResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return tap($next($request), static fn (Response $response) => $response->headers->set('X-Host-Middleware', 'ran'));
    }
}
