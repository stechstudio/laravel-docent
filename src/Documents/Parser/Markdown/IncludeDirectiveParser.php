<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use STS\Docent\Documents\Parser\Markdown\Node\IncludeDirectiveBlock;

/**
 * A self-contained `:::include name="x"` directive occupying a single line.
 */
final class IncludeDirectiveParser extends AbstractBlockContinueParser
{
    private IncludeDirectiveBlock $block;

    public function __construct(string $name)
    {
        $this->block = new IncludeDirectiveBlock($name);
    }

    public function getBlock(): IncludeDirectiveBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        // Self-contained: never continues onto subsequent lines.
        return BlockContinue::none();
    }
}
