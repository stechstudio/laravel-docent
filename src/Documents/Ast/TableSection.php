<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class TableSection extends Node
{
    public function __construct(
        public bool $header = false,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
