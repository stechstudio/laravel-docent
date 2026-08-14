<?php

declare(strict_types=1);

namespace STS\Docent\Sharing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use STS\Docent\Http\Middleware\ShareCredential;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Sites\SiteConfig;

/**
 * Mints and verifies share links: a single documentation page readable by
 * someone who is not signed in, without opening the rest of the site.
 *
 * A share token is an *alternative credential*, not a hole in the guard.
 * Nothing is ungated; the token satisfies the host's guard for one path, and
 * only for the routes {@see ShareCredential}
 * allows it to.
 *
 * One instance per site, so a token minted for one site's page is never valid
 * against another's.
 */
final class Sharing
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly SiteConfig $config,
        private readonly DocumentationMode $mode,
        private readonly string $key,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('share.enabled', false);
    }

    /**
     * Whether this viewer may mint share links. An undefined gate answers
     * false for everyone, which is the right direction to fail: publishing
     * gated documentation should never be the default.
     */
    public function canShare(?Authenticatable $user): bool
    {
        if (! $this->enabled() || $user === null) {
            return false;
        }

        return Gate::forUser($user)->allows((string) $this->config->get('share.gate', 'shareDocentPage'));
    }

    /** The share URL for a page, expiring in `$days` (clamped to `share.max_ttl`). */
    public function urlFor(string $slug, ?int $days = null): string
    {
        $url = $slug === ''
            ? route('docent.'.$this->key.'.home')
            : route('docent.'.$this->key.'.show', ['slug' => $slug]);

        return $this->append($url, $this->today() + $this->lifetime($days));
    }

    /**
     * Carry the active share credential onto a URL the share render emits, so
     * an anonymous reader can load the page's images and stylesheet. Returns
     * the URL untouched outside a share render.
     *
     * Each URL is signed for its own path, so a page token cannot be edited
     * into a fetch against an unrelated upload. Asset tokens expire on the
     * same day as the page link that authorized the render: a shared page and
     * its images die together.
     */
    public function decorate(string $url): string
    {
        $day = $this->mode->shareExpiryDay();

        return $day === null ? $url : $this->append($url, $day);
    }

    /**
     * The expiry day of a valid share credential for this exact request path,
     * or null when the request carries none.
     */
    public function credentialFor(Request $request): ?int
    {
        $token = $request->query('s');

        if (! is_string($token) || $token === '') {
            return null;
        }

        return ShareToken::expiryDay(
            $this->path($request->getPathInfo()),
            $token,
            $this->signingKey(),
            $this->today(),
        );
    }

    private function append(string $url, int $day): string
    {
        $token = ShareToken::mint($this->path((string) parse_url($url, PHP_URL_PATH)), $day, $this->signingKey());

        return $url.(str_contains($url, '?') ? '&' : '?').'s='.$token;
    }

    /**
     * Days requested, clamped to the configured ceiling. A host that lowers
     * `max_ttl` shortens links minted from then on; already-minted tokens
     * carry their own day and are unaffected.
     */
    private function lifetime(?int $days): int
    {
        $max = max(1, (int) $this->config->get('share.max_ttl', 90));

        return max(1, min($days ?? (int) $this->config->get('share.ttl', 30), $max));
    }

    /**
     * Both sides of a comparison have to agree on one spelling of a path.
     * `route()` percent-encodes while `getPathInfo()` does not decode, so
     * decode both, and treat a trailing slash as noise.
     */
    private function path(string $path): string
    {
        $decoded = rtrim(rawurldecode($path), '/');

        return $decoded === '' ? '/' : $decoded;
    }

    private function today(): int
    {
        return intdiv(Date::now()->getTimestamp(), self::SECONDS_PER_DAY);
    }

    /**
     * Signing key for this site. Deriving from the application key means a
     * share link is only ever valid against the deployment that minted it;
     * folding in `share.salt` gives an operator one config change that
     * invalidates every outstanding link at once.
     */
    private function signingKey(): string
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('Docent share links require APP_KEY to be set.');
        }

        return hash_hmac(
            'sha256',
            'docent-share|'.$this->key.'|'.(string) $this->config->get('share.salt', ''),
            $appKey,
        );
    }
}
