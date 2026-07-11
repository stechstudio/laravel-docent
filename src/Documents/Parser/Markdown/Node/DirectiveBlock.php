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
     * @param  int  $fenceLength  Colon count of the opening fence. Closing
     *                            fences match by length so directives can nest
     *                            with distinct fences (`::::cards` around
     *                            `:::card`), CommonMark-fenced-code style.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes = [],
        public readonly ?string $shorthand = null,
        public readonly int $fenceLength = 3,
    ) {
        parent::__construct();
    }
}
