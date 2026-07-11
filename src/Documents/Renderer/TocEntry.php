<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

final class TocEntry
{
    /**
     * @param  list<TocEntry>  $children
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly int $level,
        public array $children = [],
    ) {}
}
