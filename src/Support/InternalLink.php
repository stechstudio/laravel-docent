<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * Resolves markdown link destinations to documentation page slugs, with
 * file-path semantics: relative destinations (`installation`,
 * `../billing/overview`) resolve against the current page's directory, and
 * `/`-rooted destinations are internal only when they fall inside the docs
 * route prefix. The single source of truth shared by the HTML renderer and
 * `docent:check`, so a link renders and validates identically.
 */
final class InternalLink
{
    /**
     * Resolve a destination to an internal target, or null when it is external
     * (absolute URL, protocol-relative, mailto/tel, pure anchor, or an
     * application path outside the docs prefix). `suffix` preserves any
     * `#fragment`/`?query` verbatim for URL reconstruction.
     *
     * @return ?array{slug: string, suffix: string}
     */
    public static function resolve(string $destination, string $baseDir, string $routePrefix): ?array
    {
        if ($destination === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $destination) === 1) {
            return null;
        }

        $suffix = '';
        $path = $destination;

        if (preg_match('/[#?]/', $destination, $match, PREG_OFFSET_CAPTURE) === 1) {
            $path = substr($destination, 0, $match[0][1]);
            $suffix = substr($destination, $match[0][1]);
        }

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return self::rooted($path, $suffix, $routePrefix);
        }

        return ['slug' => self::relative($path, $baseDir), 'suffix' => $suffix];
    }

    /**
     * @return ?array{slug: string, suffix: string}
     */
    private static function rooted(string $path, string $suffix, string $routePrefix): ?array
    {
        $root = '/'.trim($routePrefix, '/');

        if (rtrim($path, '/') === $root) {
            return ['slug' => '', 'suffix' => $suffix];
        }

        if (str_starts_with($path, $root.'/')) {
            return ['slug' => trim(substr($path, strlen($root) + 1), '/'), 'suffix' => $suffix];
        }

        // An absolute path outside the docs prefix is an application URL.
        return null;
    }

    private static function relative(string $path, string $baseDir): string
    {
        $segments = $baseDir === '' ? [] : explode('/', $baseDir);

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
