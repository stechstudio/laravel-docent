<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * Path arithmetic for images stored alongside the Markdown that references them.
 *
 * A screenshot that lives next to its page and moves with it is the authoring
 * experience worth having, but a page-relative `src` is meaningless to a
 * browser: it resolves against the page's URL, which is a Docent route, not a
 * directory. So relative references are normalized here to a path relative to
 * the docs root, rendered as a URL on the docs image route, and streamed back
 * through it.
 *
 * The renderer, the `missing-image` check, and the controller all resolve
 * through this class so the check can never confirm a file the route would
 * refuse to serve.
 */
final class DocsImagePath
{
    /** Extensions Docent will stream, and the content type it sends for each. */
    private const MIME_TYPES = [
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    /**
     * Normalize a page-relative reference to a path relative to the docs root,
     * resolving `.` and `..` segments. Null when the reference is not a local
     * relative path (absolute, protocol-relative, `data:`, a bare anchor) or
     * when it climbs out of the docs tree.
     */
    public static function relative(string $url, string $directory): ?string
    {
        if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|\/|#)/i', $url) === 1) {
            return null;
        }

        $path = preg_replace('/[#?].*$/', '', $url) ?? $url;
        $base = trim($directory, '/');
        $segments = [];

        foreach (explode('/', ($base === '' ? '' : $base.'/').$path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment !== '..') {
                $segments[] = $segment;

                continue;
            }

            // A segment is never null, so an exhausted stack means the
            // reference climbed above the docs root.
            if (array_pop($segments) === null) {
                return null;
            }
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * Resolve a docs-root-relative path to a readable file inside that root.
     * Null when the extension is not servable, the file is missing, or the
     * resolved path escapes the root — `realpath` is used on both sides, so a
     * symlink pointing out of the tree is caught along with `..` traversal.
     */
    public static function file(string $root, string $path): ?string
    {
        $normalized = self::relative($path, '');

        if ($normalized === null || ! self::servable($normalized)) {
            return null;
        }

        $realRoot = realpath($root);

        if ($realRoot === false) {
            return null;
        }

        $file = realpath($realRoot.DIRECTORY_SEPARATOR.$normalized);

        return $file !== false
            && is_file($file)
            && str_starts_with($file, $realRoot.DIRECTORY_SEPARATOR)
            ? $file
            : null;
    }

    public static function servable(string $path): bool
    {
        return self::mimeType($path) !== null;
    }

    public static function mimeType(string $path): ?string
    {
        return self::MIME_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /** @return list<string> */
    public static function extensions(): array
    {
        return array_keys(self::MIME_TYPES);
    }
}
