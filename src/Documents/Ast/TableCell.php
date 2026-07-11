<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class TableCell extends Node
{
    /**
     * @param  ?string  $align  One of "left", "right", "center", or null.
     */
    public function __construct(
        public bool $header = false,
        public ?string $align = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
