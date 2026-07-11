<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use Illuminate\Support\Str;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags pages whose slugs collide case-insensitively — distinct source files
 * that would resolve to the same URL on a case-insensitive filesystem or router.
 */
final class DuplicateSlugCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        /** @var array<string, list<string>> $byLower */
        $byLower = [];

        foreach ($context->pages() as $page) {
            $byLower[Str::lower($page->slug)][] = $page->slug;
        }

        foreach ($byLower as $slugs) {
            if (count($slugs) < 2) {
                continue;
            }

            foreach ($slugs as $slug) {
                yield Issue::error(
                    'duplicate-slug',
                    $slug,
                    'Slug collides (case-insensitively) with: '.implode(', ', array_diff($slugs, [$slug])).'.',
                );
            }
        }
    }
}
