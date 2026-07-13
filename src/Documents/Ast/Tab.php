<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/** One labeled panel inside {@see Tabs}. */
final class Tab extends Node
{
    public function __construct(
        public string $label = '',
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
