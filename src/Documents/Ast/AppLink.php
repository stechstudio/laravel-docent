<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class AppLink extends Node
{
    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public AppLinkKind $kind,
        public string $key,
        public array $parameters = [],
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
