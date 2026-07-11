<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use STS\Docent\DocentManager;

/**
 * The admin page tree: every database page (drafts included) and every
 * filesystem page, flat, for the UI to group.
 */
final class TreeController
{
    public function __invoke(DocentManager $docent): JsonResponse
    {
        return response()->json(['pages' => $docent->adminTree()]);
    }
}
