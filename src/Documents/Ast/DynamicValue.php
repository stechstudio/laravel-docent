<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class DynamicValue extends Node
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public string $key,
        public array $arguments = [],
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
