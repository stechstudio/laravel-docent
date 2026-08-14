<?php

declare(strict_types=1);

namespace STS\Docent\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Sharing\Sharing;
use STS\Docent\Sites\SiteRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a share token stand in for the host's guard on one path.
 *
 * The service provider sorts this ahead of `AuthenticatesRequests` in the
 * kernel's middleware priority map, so it runs after the session starts and
 * before the guard, wherever the host wrote `auth`. Hosts add nothing.
 *
 * Three outcomes, and the first one matters most: a signed-in reader always
 * gets their own page in their own context. A share link is frequently sent
 * *because* the recipient needs to see something as themselves, so a valid
 * token never downgrades someone who could already read the page.
 */
final class ShareCredential
{
    /**
     * Route suffixes a share token may satisfy: the page, and the images and
     * stylesheet that page needs to look like itself.
     *
     * This list is the security boundary. Search, the assistant, insights,
     * the widget, llms.txt, the sitemap, and every admin route are absent on
     * purpose and refuse the credential no matter how valid the token.
     */
    private const CREDENTIALED = ['home', 'show', 'image', 'upload', 'asset'];

    public function __construct(
        private readonly SiteRegistry $sites,
        private readonly DocumentationMode $mode,
        private readonly Router $router,
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next, string $site): Response
    {
        $sharing = $this->sharingFor($site);

        // Ordinary reading never reaches any of the work below.
        if (! $sharing->enabled() || ! $request->has('s') || ! $this->credentialed($request, $site)) {
            return $next($request);
        }

        // Signed in: their page, their context, token inert — whether the
        // token verifies or not, and without spending their limiter budget on
        // a link that merely went stale.
        if ($this->authenticated($request, $site)) {
            return $next($request);
        }

        $day = $sharing->credentialFor($request);

        if ($day === null) {
            return $this->rejected($request, $next, $site);
        }

        $this->mode->enableShare($day);

        return $this->withoutGuard($request, $site);
    }

    /**
     * Whether the reader is signed in as far as *this route* is concerned.
     *
     * `$request->user()` would answer for the default guard alone, so a host
     * routing its documentation through `auth:admin` would have every one of
     * its signed-in admins handed the anonymous render instead of their own
     * page. The guards the route actually names are right there in its own
     * authentication middleware, so ask those.
     */
    private function authenticated(Request $request, string $site): bool
    {
        foreach ($this->guards($request, $site) as $guard) {
            if (Auth::guard($guard)->check()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The guards this route authenticates against. `auth` names the default
     * one, `auth:admin,web` names two, and a route with no authentication
     * middleware at all falls back to the default.
     *
     * @return list<?string>
     */
    private function guards(Request $request, string $site): array
    {
        $guards = [];

        foreach ($this->gathered($request) as $middleware) {
            if (! $this->isGuard($middleware, $site)) {
                continue;
            }

            $parameters = Str::after($middleware, ':');

            $guards = [...$guards, ...($parameters === $middleware ? [null] : explode(',', $parameters))];
        }

        return $guards === [] ? [null] : $guards;
    }

    /**
     * Continue down the stack with the host's authentication guard taken out,
     * and nothing else.
     *
     * The obvious shortcut — running the matched action here and returning
     * its response — skips *every* middleware below this one, not just the
     * guard: a host's security headers, its response shaping, Laravel's own
     * `SubstituteBindings`. A share response would quietly differ from every
     * other response the application serves.
     *
     * Excluding the guard from the matched route would be tidier still, but
     * `Route::withoutMiddleware()` mutates the route object, and under Octane
     * that object outlives the request — one share link would strip `auth`
     * from the page for everyone who asked for it afterwards. Rebuilding the
     * tail of the pipeline touches nothing that survives the request.
     */
    private function withoutGuard(Request $request, string $site): Response
    {
        return (new Pipeline($this->container))
            ->send($request)
            ->through($this->remainingMiddleware($request, $site))
            ->then(fn (Request $request): Response => $this->router->prepareResponse(
                $request,
                $request->route()?->run(),
            ));
    }

    /**
     * Everything the router would still have run after this middleware, less
     * the guard the token stands in for.
     *
     * @return list<string>
     */
    private function remainingMiddleware(Request $request, string $site): array
    {
        $gathered = $this->gathered($request);
        $position = array_search(self::class.':'.$site, $gathered, strict: true);

        return array_values(array_filter(
            array_slice($gathered, $position === false ? 0 : $position + 1),
            fn (string $middleware): bool => ! $this->isGuard($middleware, $site),
        ));
    }

    /**
     * The route's middleware with aliases and groups already resolved to class
     * names, which is the form both the priority map and this class match on.
     *
     * @return list<string>
     */
    private function gathered(Request $request): array
    {
        return array_values(array_filter(
            $this->router->gatherRouteMiddleware($request->route()),
            is_string(...),
        ));
    }

    /** Whether this middleware is the authentication the token stands in for. */
    private function isGuard(string $middleware, string $site): bool
    {
        $anchor = (string) $this->sites->siteConfig($site)->get('share.before', AuthenticatesRequests::class);

        return is_a(Str::before($middleware, ':'), $anchor, allow_string: true);
    }

    /**
     * A request carrying a token that did not verify is a guess. Count it
     * against the limiter, then hand it to the guard, which answers exactly
     * as it would for any other anonymous visitor — so a failed guess and an
     * absent token are indistinguishable from outside.
     *
     * Verified tokens deliberately do not consume the budget. A shared page
     * pulls its stylesheet and every image through this same middleware, so
     * charging for success would rate-limit ordinary reading.
     */
    private function rejected(Request $request, Closure $next, string $site): Response
    {
        [$attempts, $seconds] = $this->limit($site);
        $key = 'docent-share|'.$site.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            abort(429, headers: ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, $seconds);

        return $next($request);
    }

    /** @return array{0: int, 1: int} attempts, and the window in seconds */
    private function limit(string $site): array
    {
        $configured = explode(',', (string) $this->sites->siteConfig($site)->get('share.throttle', '60,1'));

        return [max(1, (int) $configured[0]), max(1, (int) ($configured[1] ?? 1)) * 60];
    }

    private function credentialed(Request $request, string $site): bool
    {
        $name = $request->route()?->getName();
        $prefix = 'docent.'.$site.'.';

        return is_string($name)
            && str_starts_with($name, $prefix)
            && in_array(substr($name, strlen($prefix)), self::CREDENTIALED, strict: true);
    }

    private function sharingFor(string $site): Sharing
    {
        $sharing = $this->sites->serviceFor($site, Sharing::class);

        return $sharing instanceof Sharing
            ? $sharing
            : throw new RuntimeException('The Docent site graph did not contain a sharing service.');
    }
}
