<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class IncludeNode extends Node
{
    public function __construct(
        public string $name,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
