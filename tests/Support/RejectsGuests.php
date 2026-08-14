<?php

declare(strict_types=1);

namespace STS\Docent\Tests\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guard of the kind `share.before` exists for: it turns guests away without
 * implementing `AuthenticatesRequests` or extending Laravel's `Authenticate`,
 * so nothing about it is in the kernel's priority map by default.
 */
final class RejectsGuests
{
    public function handle(Request $request, Closure $next): Response
    {
        return Auth::check() ? $next($request) : redirect('/login');
    }
}
