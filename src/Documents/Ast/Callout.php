<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class Callout extends Node
{
    public function __construct(
        public CalloutType $type,
        public ?string $title = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
