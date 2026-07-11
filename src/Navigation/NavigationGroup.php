<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

/**
 * A sidebar group (a directory of pages), holding ordered page items and,
 * optionally, one level of nested sub-groups.
 */
final class NavigationGroup
{
    /**
     * @param  list<NavigationItem>  $items
     * @param  list<NavigationGroup>  $groups
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly array $items = [],
        public readonly array $groups = [],
    ) {}
}
