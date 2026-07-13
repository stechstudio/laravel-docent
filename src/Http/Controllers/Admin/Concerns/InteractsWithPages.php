<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\DocentManager;

/**
 * Shared page-resolution and slug-validation helpers for the admin controllers.
 * Slug rules mirror the reader's: lowercase alphanumeric segments joined by
 * slashes, no leading underscore except the `_partials/` namespace, and never a
 * `..` traversal.
 */
trait InteractsWithPages
{
    /**
     * @return Builder<DocentPage>
     */
    protected function pageQuery()
    {
        return DocentPage::on($this->connection());
    }

    /**
     * The database page for a slug, or a 404 — the reader never distinguishes a
     * missing page from a forbidden one, and neither does the admin.
     */
    protected function findPageOrFail(string $slug): DocentPage
    {
        return $this->pageQuery()->where('slug', $slug)->firstOr(fn () => abort(404));
    }

    /**
     * Resolve a slug route parameter: reject path traversal, and map the
     * `_home` wire alias to the home page's real slug — the empty string,
     * which cannot travel as a URL path segment. Underscored slugs are
     * reserved, so the alias can never collide with an actual page.
     */
    protected function resolveSlug(string $slug): string
    {
        if (str_contains($slug, '..')) {
            abort(404);
        }

        return $slug === '_home' ? '' : $slug;
    }

    /**
     * Validate a slug for a create/update write, raising a 422 when malformed.
     */
    protected function assertValidSlug(string $slug): void
    {
        if (! $this->isValidSlug($slug)) {
            throw ValidationException::withMessages([
                'slug' => 'The slug must be lowercase alphanumeric segments separated by slashes or hyphens.',
            ]);
        }
    }

    protected function assertUnlocked(string $slug, DocentManager $docent): void
    {
        if ($docent->filesystemSlugLocked($slug)) {
            abort(403, "The repository page '{$slug}' is locked and cannot be changed in Docent admin.");
        }
    }

    private function isValidSlug(string $slug): bool
    {
        // The empty slug is the docs home page (root index.md equivalent).
        if ($slug === '') {
            return true;
        }

        if (str_starts_with($slug, '_partials/')) {
            $slug = substr($slug, strlen('_partials/'));
        } elseif (str_starts_with($slug, '_')) {
            return false;
        }

        return $slug !== '' && preg_match('#^[a-z0-9]([a-z0-9/-]*[a-z0-9])?$#', $slug) === 1;
    }

    private function connection(): ?string
    {
        $connection = config('docent.database.connection');

        return $connection === null ? null : (string) $connection;
    }

    /**
     * Read the ProseMirror document from the RAW request body. Laravel's global
     * TrimStrings middleware mutates nested input strings, but whitespace inside
     * rich-text nodes is meaningful — "Plan: " before an inline value chip must
     * keep its trailing space. Falls back to (trimmed) input for non-JSON bodies.
     *
     * @return array<string, mixed>|null
     */
    protected function rawTiptap(Request $request): ?array
    {
        $body = json_decode($request->getContent(), true);

        if (is_array($body) && is_array($body['content_tiptap'] ?? null)) {
            return $body['content_tiptap'];
        }

        $input = $request->input('content_tiptap');

        return is_array($input) ? $input : null;
    }
}
