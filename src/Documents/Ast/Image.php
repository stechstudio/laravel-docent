<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class Image extends Node
{
    public function __construct(
        public string $url,
        public string $alt = '',
        public ?string $title = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
