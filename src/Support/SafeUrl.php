<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * Neutralizes dangerous URL schemes on author-supplied link destinations before
 * they reach an HTML `href`. Relative paths, `#` anchors, and query-only
 * destinations are always safe; absolute URLs are allowed only for an explicit
 * scheme allowlist. Anything else (notably `javascript:` and `data:`) is
 * rejected, so a markdown link or card/video href cannot smuggle script.
 */
final class SafeUrl
{
    /** Schemes permitted on an absolute href. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel', 'ftp'];

    /**
     * Return the destination unchanged when it is safe to place in an `href`,
     * or null when its scheme is not allowlisted. A null result means "do not
     * emit a link" — render the label as plain text instead.
     */
    public static function filter(string $destination): ?string
    {
        $trimmed = ltrim($destination);

        // Relative path, root-relative path, pure anchor, or query — no scheme.
        if ($trimmed === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $trimmed) !== 1) {
            return $destination;
        }

        // Protocol-relative `//host` has no scheme token but is an absolute URL
        // to http(s); allow it (the regex above already let it fall through only
        // when it starts with `//`, handled here for clarity).
        if (str_starts_with($trimmed, '//')) {
            return $destination;
        }

        $scheme = strtolower((string) strstr($trimmed, ':', true));

        return in_array($scheme, self::ALLOWED_SCHEMES, true) ? $destination : null;
    }
}
