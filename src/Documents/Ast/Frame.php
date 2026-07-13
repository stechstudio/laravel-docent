<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/** A figure-like presentation wrapper for an image and optional caption. */
final class Frame extends Node
{
    public function __construct(
        public ?string $caption = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
