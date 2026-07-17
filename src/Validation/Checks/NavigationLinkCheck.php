<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Validates persistent navigation-link structure and resolvable internal
 * targets. Runtime resolution remains defensive and skips invalid entries.
 */
final class NavigationLinkCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach (['links', 'topbar'] as $list) {
            yield from $this->checkList($list, $context);
        }
    }

    /** @return iterable<Issue> */
    private function checkList(string $list, CheckContext $context): iterable
    {
        $links = $context->docent?->config('navigation.'.$list, []) ?? [];

        if (! is_array($links)) {
            yield Issue::error('invalid-navigation-link', 'navigation.'.$list, 'Navigation links must be an array.', 1);

            return;
        }

        $slugs = $context->slugSet();

        foreach ($links as $index => $link) {
            $location = 'navigation.'.$list.'.'.((int) $index);

            if (! is_array($link)) {
                yield Issue::error('invalid-navigation-link', $location, 'Navigation link must be an array.', 1);

                continue;
            }

            if (! isset($link['label']) || ! is_string($link['label']) || trim($link['label']) === '') {
                yield Issue::error('invalid-navigation-link', $location, 'Navigation link requires a non-empty label.', 1);
            }

            $targets = array_values(array_filter(
                ['url', 'page', 'route'],
                static fn (string $target): bool => isset($link[$target]) && is_string($link[$target]) && trim($link[$target]) !== '',
            ));

            if (count($targets) !== 1) {
                yield Issue::error('invalid-navigation-link', $location, 'Navigation link must define exactly one of url, page, or route.', 1);

                continue;
            }

            $target = $targets[0];
            $value = trim($link[$target]);

            if ($target === 'page' && ! isset($slugs[$value])) {
                yield Issue::error('unknown-navigation-page', $location, 'Navigation link points at nonexistent page "'.$value.'".', 1);
            }

            if ($target === 'route' && ! $context->routeExists($value)) {
                yield Issue::error('unknown-navigation-route', $location, 'Navigation link points at undefined route "'.$value.'".', 1);
            }
        }
    }
}
