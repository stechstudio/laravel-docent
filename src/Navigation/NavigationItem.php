<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

/**
 * A single navigable page in the sidebar.
 */
final class NavigationItem
{
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly string $url,
        public readonly ?string $description = null,
        public readonly bool $searchExcluded = false,
    ) {}

    public function active(string $currentSlug): bool
    {
        return $this->slug === $currentSlug;
    }
}
