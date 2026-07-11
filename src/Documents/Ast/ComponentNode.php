<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class ComponentNode extends Node
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
