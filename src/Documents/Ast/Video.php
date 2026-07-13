<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

/** A provider embed or directly playable video file with an optional caption. */
final class Video extends Node
{
    public function __construct(
        public string $url = '',
        public ?string $caption = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
