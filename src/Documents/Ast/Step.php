<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/** One titled item inside a {@see Steps} sequence. */
final class Step extends Node
{
    public function __construct(
        public string $title = '',
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
