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
     * @param  ?NavigationItem  $index  The directory's own `index.md` page, when one
     *                                  exists and is visible to the viewer. The sidebar
     *                                  renders the group header as a link to it rather
     *                                  than listing it among the group's items.
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly array $items = [],
        public readonly array $groups = [],
        public readonly ?NavigationItem $index = null,
    ) {}

    /**
     * Whether this group (or any nested group) holds the given page slug.
     * Drives breadcrumbs and auto-expansion of the active section.
     */
    public function contains(string $slug): bool
    {
        if ($this->index?->slug === $slug) {
            return true;
        }

        foreach ($this->items as $item) {
            if ($item->slug === $slug) {
                return true;
            }
        }

        foreach ($this->groups as $group) {
            if ($group->contains($slug)) {
                return true;
            }
        }

        return false;
    }
}
