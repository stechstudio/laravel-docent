<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use STS\Docent\Documents\Parser\Markdown\Node\ComponentBlock;

final class ComponentBlockParser extends AbstractBlockContinueParser
{
    private ComponentBlock $block;

    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(string $name, array $attributes)
    {
        $this->block = new ComponentBlock($name, $attributes);
    }

    public function getBlock(): ComponentBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        return BlockContinue::none();
    }
}
