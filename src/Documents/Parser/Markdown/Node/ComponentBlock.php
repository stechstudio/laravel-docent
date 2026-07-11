<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A block-level `<docs-component name="x" foo="bar" />` tag.
 */
final class ComponentBlock extends AbstractBlock
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes = [],
    ) {
        parent::__construct();
    }
}
