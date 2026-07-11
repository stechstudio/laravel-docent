<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Docent\DocentManager;

/**
 * Renders an unsaved draft through the real pipeline with the admin's own
 * context, returning HTML, a table of contents, and the inline reference
 * checks. No persistence.
 */
final class PreviewController
{
    public function __invoke(Request $request, DocentManager $docent): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['present', 'string'],
            'front_matter' => ['array'],
        ]);

        return response()->json($docent->previewDraft(
            $request->string('content')->toString(),
            $validated['front_matter'] ?? [],
            $docent->contextFor($request),
        ));
    }
}
