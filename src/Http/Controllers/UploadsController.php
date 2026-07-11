<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an admin-uploaded image from the configured upload disk. Serving
 * through a route (instead of Storage::url) means uploads work on any disk —
 * no storage:link, no public bucket, no unsigned S3 URLs — and inherit the
 * docs route group's middleware, so private docs keep their images private.
 * Filenames are content-hashed, so responses are immutable-cacheable.
 */
final class UploadsController
{
    public function __invoke(string $path): Response
    {
        abort_if(str_contains($path, '..'), 404);
        abort_unless(str_starts_with($path, 'docent/'), 404);

        $disk = Storage::disk((string) config('docent.admin.disk', 'public'));

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
