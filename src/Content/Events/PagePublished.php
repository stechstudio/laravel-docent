<?php

declare(strict_types=1);

namespace STS\Docent\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;
use STS\Docent\Content\Models\DocentPage;

/** A database-authored page was published. */
final class PagePublished
{
    use Dispatchable;

    public function __construct(
        public readonly DocentPage $page,
    ) {}
}
