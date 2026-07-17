<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Illuminate\Support\Arr;

/**
 * Read-only view of one site's effective configuration. Lookups cascade
 * site entry → top-level shared default → caller default. The SITE_ONLY
 * sections never fall back to the top level — a route prefix or content
 * path is meaningless as a cross-site default.
 *
 * An explicit null in a site entry is indistinguishable from an absent key
 * and cascades; sites cannot null-out a shared value, only replace it.
 */
final class SiteConfig
{
    private const SITE_ONLY = ['name', 'description', 'route', 'filesystem', 'admin', 'navigation', 'layouts'];

    /**
     * @param  array<string, mixed>  $config  The full `docent` config array.
     */
    public function __construct(
        public readonly string $key,
        private readonly array $config,
    ) {}

    public function get(string $path, mixed $default = null): mixed
    {
        $value = Arr::get($this->config, 'sites.'.$this->key.'.'.$path);

        if ($value !== null) {
            return $value;
        }

        if ($this->isSiteOnly($path)) {
            return $default;
        }

        return Arr::get($this->config, $path, $default) ?? $default;
    }

    private function isSiteOnly(string $path): bool
    {
        $section = str_contains($path, '.') ? strstr($path, '.', true) : $path;

        return in_array($section, self::SITE_ONLY, true);
    }
}
