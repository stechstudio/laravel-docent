<?php

declare(strict_types=1);

namespace STS\Docent\Documents;

use STS\Docent\Documents\Ast\Node;

/**
 * The canonical document model: the AST root node plus its front matter.
 */
final class Document extends Node
{
    public function __construct(
        public readonly FrontMatter $frontMatter,
        ?int $line = null,
    ) {
        parent::__construct($line);
    }

    public function frontMatter(): FrontMatter
    {
        return $this->frontMatter;
    }
}
