<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class HtmlBlock extends Node
{
    public function __construct(
        public string $html,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
