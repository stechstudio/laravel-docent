<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `Docent::suggest()` registrations that point at pages which do not
 * exist. Suggestions rot silently otherwise: the widget drops missing slugs at
 * runtime, so a renamed page simply stops being suggested with no error
 * anywhere. Patterns themselves are not validated; they match route names by
 * wildcard and cannot be proven wrong statically.
 */
final class UnknownSuggestionCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        $slugs = [];

        foreach ($context->pages() as $page) {
            $slugs[$page->slug] = true;
        }

        foreach ($context->registry()->suggestions() as $pattern => $suggested) {
            foreach ($suggested as $slug) {
                if (! isset($slugs[$slug])) {
                    yield Issue::error('unknown-suggestion', $slug, 'Suggestion for "'.$pattern.'" points at nonexistent page "'.$slug.'".', 1);
                }
            }
        }
    }
}
