<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A container directive: `:::name key="value"` ... `:::`.
 */
final class DirectiveBlock extends AbstractBlock
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes = [],
        public readonly ?string $shorthand = null,
    ) {
        parent::__construct();
    }
}
