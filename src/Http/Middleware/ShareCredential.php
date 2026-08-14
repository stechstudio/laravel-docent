<?php

declare(strict_types=1);

namespace STS\Docent\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Routing\Router;
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
        // Signed in: their page, their context, token inert.
        if ($request->user() !== null) {
            return $next($request);
        }

        $sharing = $this->sharingFor($site);

        if (! $sharing->enabled() || ! $this->credentialed($request, $site)) {
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
        $gathered = $this->router->gatherRouteMiddleware($request->route());
        $anchor = (string) $this->sites->siteConfig($site)->get('share.before', AuthenticatesRequests::class);

        $position = array_search(self::class.':'.$site, $gathered, strict: true);

        return array_values(array_filter(
            array_slice($gathered, $position === false ? 0 : $position + 1),
            fn (mixed $middleware): bool => is_string($middleware)
                && ! is_a(Str::before($middleware, ':'), $anchor, allow_string: true),
        ));
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
        if (! $request->has('s')) {
            return $next($request);
        }

        [$attempts, $seconds] = $this->limit($site);
        $key = 'docent-share|'.$site.'|'.$request->ip();

        abort_if(RateLimiter::tooManyAttempts($key, $attempts), 429);

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
