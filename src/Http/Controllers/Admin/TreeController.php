<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use STS\Docent\Admin\Editor;
use STS\Docent\Support\Icon;

/**
 * The admin page tree: every database page (drafts included) and every
 * filesystem page, flat, for the UI to group — plus the group metadata list,
 * each entry carrying its inlined `iconSvg` so the tree renders group icons
 * without waiting for the lazy icons endpoint.
 */
final class TreeController
{
    public function __invoke(Editor $editor): JsonResponse
    {
        return response()->json([
            'pages' => $editor->adminTree(),
            'groups' => array_map(
                static fn (array $group): array => [...$group, 'iconSvg' => $group['icon'] !== null ? Icon::svg($group['icon']) : null],
                $editor->adminGroups(),
            ),
        ]);
    }
}
