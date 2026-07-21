<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

/**
 * Exposes redirect aliases whose slug is also owned by a real page.
 *
 * @internal
 */
interface RedirectCollisionRepository
{
    /** @return list<string> */
    public function redirectCollisions(): array;
}
