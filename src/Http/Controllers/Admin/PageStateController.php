<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Docent\DocentManager;
use STS\Docent\Http\Controllers\Admin\Concerns\InteractsWithPages;

/**
 * Draft lifecycle for database pages: publish, unpublish, revert to a past
 * revision, and override a filesystem page into a new database draft. Each
 * returns the refreshed editor payload.
 */
final class PageStateController
{
    use InteractsWithPages;

    public function publish(string $slug, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);
        $this->findPageOrFail($slug)->publish();

        return response()->json($docent->adminDetail($slug));
    }

    public function unpublish(string $slug, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);
        $this->findPageOrFail($slug)->unpublish();

        return response()->json($docent->adminDetail($slug));
    }

    public function revert(string $slug, int $revision, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);

        $page = $this->findPageOrFail($slug);
        $target = $page->revisions()->whereKey($revision)->firstOr(fn () => abort(404));

        $page->revertTo($target);

        return response()->json($docent->adminDetail($slug));
    }

    public function override(string $slug, Request $request, DocentManager $docent): JsonResponse
    {
        $this->guardTraversal($slug);

        if ($this->pageQuery()->where('slug', $slug)->exists()) {
            abort(409, 'A database page already exists for this slug.');
        }

        $id = $request->user()?->getAuthIdentifier();

        $page = $docent->overrideFromFilesystem($slug, $id === null ? null : (int) $id);

        if ($page === null) {
            abort(404);
        }

        return response()->json($docent->adminDetail($slug));
    }
}
