<?php

declare(strict_types=1);

namespace STS\Docent\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;
use STS\Docent\Content\Models\DocentPage;

/**
 * A database-authored page was created or updated (a revision may have been
 * snapshotted). `$created` is true only on the first save of a new page.
 */
final class PageSaved
{
    use Dispatchable;

    public function __construct(
        public readonly DocentPage $page,
        public readonly bool $created,
    ) {}
}
