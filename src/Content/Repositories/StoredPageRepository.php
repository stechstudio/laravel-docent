<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

/**
 * Exposes non-deleted stored slugs (pages and partials) for diagnostics. This
 * is deliberately separate from `all()`, which enumerates only pages the
 * repository serves.
 */
interface StoredPageRepository
{
    /** @return list<string> */
    public function storedSlugs(): array;
}
