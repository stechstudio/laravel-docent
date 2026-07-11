<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class AuthorizationBlock extends Node
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public AuthorizationMode $mode,
        public string $ability,
        public array $arguments = [],
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
