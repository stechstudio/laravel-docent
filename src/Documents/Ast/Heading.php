<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class Heading extends Node
{
    public function __construct(
        public int $level,
        public string $slug = '',
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
