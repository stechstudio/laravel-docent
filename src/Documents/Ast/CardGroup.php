<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/**
 * A responsive grid of {@see Card} nodes, authored as `::::cards`. `columns`
 * caps the grid at large breakpoints (it steps down to one column on narrow
 * viewports).
 */
final class CardGroup extends Node
{
    public function __construct(
        public int $columns = 2,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
