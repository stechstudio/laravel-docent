<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Surfaces drift in a composite store: pages an earlier repository (the database
 * store) serves that also exist as files in a later one. The override is
 * intentional but never silent — each shadowed slug is reported as a warning.
 */
final class ShadowedPageCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        $repository = $context->repository();

        if (! $repository instanceof CompositeRepository) {
            return;
        }

        foreach ($repository->shadowed() as $slug) {
            yield Issue::warning(
                'shadowed-page',
                $slug,
                "Database page overrides repository file for '".$slug."'.",
            );
        }
    }
}
