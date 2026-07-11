<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class OrderedList extends Node
{
    public function __construct(
        public int $start = 1,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
