<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

/**
 * One card in a generated section-cards grid: a directory of pages (with an
 * article count) or a single page (count of null).
 */
final class SectionCard
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        public readonly ?int $count = null,
    ) {}
}
