<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown\Node;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A self-contained include directive: `:::include name="x"`.
 */
final class IncludeDirectiveBlock extends AbstractBlock
{
    public function __construct(
        public readonly string $name,
    ) {
        parent::__construct();
    }
}
