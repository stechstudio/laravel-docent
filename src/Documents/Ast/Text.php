<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class Text extends Node
{
    public function __construct(
        public string $content,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
