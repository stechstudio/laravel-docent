<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Request;
use STS\Docent\DocentManager;
use STS\Docent\Support\DocsImagePath;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams an image stored in the documentation directory, so a screenshot can
 * live next to the page it illustrates and move with it.
 *
 * Serving through a route (rather than expecting authors to copy files into
 * `public/`) means the image inherits the docs route group's middleware, so a
 * documentation site behind auth keeps its screenshots behind auth too. That is
 * the same reasoning as the `_uploads` route, and the same caveat applies:
 * protection is at the route group, not per page — an image referenced from a
 * gated page is reachable by anyone who can reach the docs site at all.
 *
 * {@see DocsImagePath} enforces the extension allowlist and confines resolution
 * to the docs root, covering `..` traversal and symlinks pointing out of it.
 * SVGs are served with a restrictive document policy so opening the raw URL
 * cannot run active content.
 */
final class DocsImageController
{
    private const SVG_CSP = "sandbox; default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $root = $this->docent->config('filesystem.path');
        $file = is_string($root) ? DocsImagePath::file($root, $path) : null;

        abort_if($file === null, 404);

        $response = response()->file($file, [
            // Documentation images change with a deploy, not on their own, but
            // the docs may be private — so revalidate against Last-Modified
            // rather than caching shared or long.
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => (string) DocsImagePath::mimeType($file),
            'X-Content-Type-Options' => 'nosniff',
            ...(DocsImagePath::mimeType($file) === 'image/svg+xml'
                ? ['Content-Security-Policy' => self::SVG_CSP]
                : []),
        ])->setAutoLastModified();

        $response->isNotModified($request);

        return $response;
    }
}
