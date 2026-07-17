<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/**
 * A card grid generated from the navigation tree, authored as
 * `:::section-cards` (top-level directories) or `:::section-cards billing`
 * (the children of one directory). Cards inherit navigation's per-viewer
 * authorization filtering, so the grid adapts to who is looking at it.
 */
final class SectionCards extends Node
{
    public function __construct(
        public string $section = '',
        public int $columns = 3,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
