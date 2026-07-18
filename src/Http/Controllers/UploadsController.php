<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use STS\Docent\DocentManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an admin-uploaded image from the configured upload disk. Serving
 * through a route (instead of Storage::url) means uploads work on any disk —
 * no storage:link, no public bucket, no unsigned S3 URLs — and inherit the
 * docs route group's middleware, so private docs keep their images private.
 * Filenames are content-hashed, so responses are immutable-cacheable in the
 * viewer's private cache by default. SVGs remain images in Docent and receive
 * a restrictive document policy so opening the raw URL cannot run active SVG.
 */
final class UploadsController
{
    /** @var array<string, string> */
    private const MIME_TYPES = [
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
    ];

    private const SVG_CSP = "sandbox; default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(string $path): Response
    {
        abort_if(str_contains($path, '..'), 404);

        // Each site serves only its own upload namespace — a shared disk must
        // never let one site's route stream another site's images.
        abort_unless(str_starts_with($path, 'docent/'.$this->docent->key().'/'), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = self::MIME_TYPES[$extension] ?? null;

        abort_if($mimeType === null, 404);

        $disk = Storage::disk((string) $this->docent->config('admin.disk', 'public'));

        abort_unless($disk->exists($path), 404);

        $cacheVisibility = $this->docent->config('admin.uploads.public_cache', false) ? 'public' : 'private';
        $headers = [
            'Cache-Control' => $cacheVisibility.', max-age=31536000, immutable',
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($extension === 'svg') {
            $headers['Content-Security-Policy'] = self::SVG_CSP;
        }

        return $disk->response($path, basename($path), $headers, 'inline');
    }
}
