<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

final class Link extends Node
{
    /**
     * @param  string|AppLink  $destination  A plain URL/slug, or an AppLink when the
     *                                       destination was authored as `{{ link:... }}`.
     */
    public function __construct(
        public string|AppLink $destination,
        public ?string $title = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
