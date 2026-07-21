<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

/**
 * A repository that can make its own sources authoritative for selected
 * slugs. Locks are source-owned metadata: another repository cannot add or
 * remove them through the composite cascade.
 *
 * @internal
 */
interface LockAwareRepository
{
    public function pageLocked(string $slug): bool;

    public function partialLocked(string $name): bool;
}
