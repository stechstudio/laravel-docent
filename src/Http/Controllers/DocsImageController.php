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
 * documentation site behind auth keeps its screenshots behind auth. That is the
 * same reasoning as the `_uploads` route, and the same caveat applies:
 * protection is at the route group, not per page — an image referenced from a
 * gated page is reachable by anyone who can reach the docs site at all.
 *
 * {@see DocsImagePath} enforces the extension allowlist and confines resolution
 * to the docs root, covering `..` traversal and symlinks pointing out of it. The
 * allowlist is a suffix check, not content inspection: documentation files are
 * reviewed repository code under the same trust model as raw HTML in Markdown,
 * so a file named `.png` is served as one.
 *
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
        $file = DocsImagePath::file($this->docent->docsPath(), $path);

        abort_if($file === null, 404);

        $mimeType = (string) DocsImagePath::mimeType($file);

        $response = response()->file($file, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            ...($mimeType === 'image/svg+xml' ? ['Content-Security-Policy' => self::SVG_CSP] : []),
        ]);

        $response->setContentDisposition('inline', basename($file));

        // `response()->file()` builds a *public* BinaryFileResponse, so the
        // visibility has to be set on the response rather than passed as a
        // header — otherwise a shared cache could hold a private site's
        // screenshot and hand it to a request that never reached the auth
        // middleware. Revalidate rather than hold: these change on deploy, at
        // the same URL, and Last-Modified makes that a cheap 304.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->setAutoLastModified();
        $response->isNotModified($request);

        return $response;
    }
}
