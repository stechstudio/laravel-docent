<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/** A disclosure with a user-facing question or title. */
final class Accordion extends Node
{
    public function __construct(
        public string $title = '',
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
