<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class ConditionBlock extends Node
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public string $condition,
        public bool $negated = false,
        public array $arguments = [],
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
