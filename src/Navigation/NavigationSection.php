<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

/**
 * One viewer-visible documentation area. The default section has no directory;
 * promoted sections map to top-level documentation directories.
 */
final class NavigationSection
{
    /**
     * @param  list<NavigationItem|NavigationGroup>  $navigation
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $directory,
        public readonly string $url,
        public readonly array $navigation,
        public readonly bool $active = false,
    ) {}
}
