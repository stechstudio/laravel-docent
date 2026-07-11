<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

use STS\Docent\Support\InternalLink;

/**
 * A single card, authored as `:::card title="..." icon="..." href="..."`. Its
 * children are the body prose. With an `href` the whole card is a link resolved
 * through the same {@see InternalLink} path as markdown
 * links; without one it renders as a static panel. Valid on its own or nested
 * inside a {@see CardGroup}.
 */
final class Card extends Node
{
    public function __construct(
        public ?string $title = null,
        public ?string $icon = null,
        public ?string $href = null,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }
}
