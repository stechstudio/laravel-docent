<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class AudienceBlock extends Node
{
    public function __construct(
        public string $audience,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
