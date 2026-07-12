<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use STS\Docent\DocentManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams the package's prebuilt CSS/JS from resources/dist so `/docs` works
 * with zero publish step. Long-lived immutable caching; the version query
 * string ({@see DocentManager::asset()}) busts the cache on content change.
 */
final class AssetController
{
    private const TYPES = [
        'docent.css' => 'text/css',
        'docent.js' => 'text/javascript',
        'docent-widget.js' => 'text/javascript',
        'docent-admin.css' => 'text/css',
        'docent-admin.js' => 'text/javascript',
    ];

    public function __invoke(DocentManager $docent, string $file): BinaryFileResponse
    {
        $type = self::TYPES[$file] ?? abort(404);
        $path = $docent->assetPath($file);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
