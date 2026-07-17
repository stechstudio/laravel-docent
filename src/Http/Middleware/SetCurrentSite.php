<?php

declare(strict_types=1);

namespace STS\Docent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use STS\Docent\Sites\CurrentSite;

final class SetCurrentSite
{
    public function __construct(private readonly CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next, string $key): mixed
    {
        $this->currentSite->set($key);

        return $next($request);
    }
}
