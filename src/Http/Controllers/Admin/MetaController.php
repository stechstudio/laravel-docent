<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use STS\Docent\Admin\Editor;

/**
 * Registry metadata for the editor's pickers: conditions, values, links,
 * components, audiences, icons, and abilities.
 */
final class MetaController
{
    public function __invoke(Editor $editor): JsonResponse
    {
        return response()->json($editor->pickerMeta());
    }
}
