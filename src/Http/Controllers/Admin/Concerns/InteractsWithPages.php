<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use STS\Docent\Content\Models\DocentPage;

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
     * Reject a path-traversal slug on any read/act route (the write routes get
     * the stricter {@see assertValidSlug()}).
     */
    protected function guardTraversal(string $slug): void
    {
        if (str_contains($slug, '..')) {
            abort(404);
        }
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

    private function isValidSlug(string $slug): bool
    {
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
}
