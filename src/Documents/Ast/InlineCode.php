<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class InlineCode extends Node
{
    public function __construct(
        public string $code,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
