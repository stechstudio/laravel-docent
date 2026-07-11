<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use STS\Docent\Support\Icon;

/**
 * The full icon set (heroicons + legacy) as name/markup pairs, powering the
 * admin icon picker. Lazily fetched by the panel on first open so the ~316
 * inline SVGs never bloat the initial page load.
 */
final class IconController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'icons' => array_map(
                static fn (string $name): array => ['name' => $name, 'svg' => Icon::svg($name)],
                Icon::names(),
            ),
        ]);
    }
}
