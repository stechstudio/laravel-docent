<?php

declare(strict_types=1);

namespace STS\Docent\Sharing;

use STS\Docent\Http\Middleware\ShareCredential;

/**
 * The share link token format: a base36 expiry day followed by a truncated
 * HMAC over the request path and that same day.
 *
 * Deliberately short. Laravel's signed URLs hex-encode a full SHA-256 digest
 * and carry a Unix timestamp beside it, which is around a hundred characters
 * of query string; a share link is pasted into support replies and sales
 * email, so width is a feature. Sixty-four bits of MAC is safe here because
 * forging one means guessing the digest for a *specific* path against a
 * rate-limited endpoint — see {@see ShareCredential}.
 *
 * Pure by design: no config, no container, no request. The composed key
 * arrives from {@see Sharing}, which is what makes the format unit-testable
 * and what lets a salt change invalidate every outstanding link at once.
 */
final class ShareToken
{
    /** Truncation width of the HMAC, in bytes. */
    private const MAC_BYTES = 8;

    /** Width of {@see self::MAC_BYTES} once base64url-encoded and unpadded. */
    private const MAC_CHARS = 11;

    public static function mint(string $path, int $day, string $key): string
    {
        return base_convert((string) $day, 10, 36).self::mac($path, $day, $key);
    }

    /**
     * The token's expiry day when it is valid for this path and has not
     * passed, otherwise null. Returning the day rather than a boolean lets a
     * share render mint asset tokens that expire alongside the page link that
     * authorized it.
     */
    public static function expiryDay(string $path, string $token, string $key, int $today): ?int
    {
        $mac = substr($token, -self::MAC_CHARS);
        $encodedDay = substr($token, 0, -self::MAC_CHARS);

        if ($encodedDay === '' || preg_match('/^[0-9a-z]+$/', $encodedDay) !== 1) {
            return null;
        }

        $day = (int) base_convert($encodedDay, 36, 10);

        return $day >= $today && hash_equals(self::mac($path, $day, $key), $mac)
            ? $day
            : null;
    }

    private static function mac(string $path, int $day, string $key): string
    {
        $digest = hash_hmac('sha256', $path.'|'.$day, $key, binary: true);

        return rtrim(strtr(base64_encode(substr($digest, 0, self::MAC_BYTES)), '+/', '-_'), '=');
    }
}
