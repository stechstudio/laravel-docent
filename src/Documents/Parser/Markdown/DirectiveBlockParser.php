<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;
use STS\Docent\Documents\Parser\Markdown\Node\DirectiveBlock;

/**
 * Continuation parser for a container directive. Directives nest, so a closing
 * `:::` fence only finalizes the innermost open directive — determined by walking
 * from the currently-active block up to its nearest directive ancestor.
 */
final class DirectiveBlockParser extends AbstractBlockContinueParser
{
    private DirectiveBlock $block;

    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(string $name, array $attributes, ?string $shorthand)
    {
        $this->block = new DirectiveBlock($name, $attributes, $shorthand);
    }

    public function getBlock(): DirectiveBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($this->isClosingFence($cursor) && $this->ownsFence($activeBlockParser)) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    private function isClosingFence(Cursor $cursor): bool
    {
        return ! $cursor->isIndented()
            && preg_match('/^:{3,}\s*$/', $cursor->getRemainder()) === 1;
    }

    /**
     * The innermost open directive owns the fence: find the nearest directive
     * ancestor of the active block and compare it to ourselves.
     */
    private function ownsFence(BlockContinueParserInterface $activeBlockParser): bool
    {
        $node = $activeBlockParser->getBlock();

        while ($node !== null && ! $node instanceof DirectiveBlock) {
            $node = $node->parent();
        }

        return $node === $this->block;
    }
}
