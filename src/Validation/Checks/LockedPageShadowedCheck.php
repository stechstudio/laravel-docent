<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Reports database rows that can no longer override a locked repository page.
 * Runtime behavior is safe; the warning exists so stale database content does
 * not disappear silently.
 */
final class LockedPageShadowedCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        $repository = $context->repository();

        if (! $repository instanceof CompositeRepository) {
            return;
        }

        foreach ($repository->lockedShadowed() as $slug) {
            yield Issue::warning(
                'locked-page-shadowed',
                $slug,
                "Database copy of '{$slug}' is ignored because the repository version is locked; delete the stale row from the database.",
            );
        }
    }
}
