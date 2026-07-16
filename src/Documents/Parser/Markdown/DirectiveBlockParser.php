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
 * Continuation parser for a container directive. Directives nest, and closing
 * fences are matched CommonMark-fenced-code style by length: a fence of N
 * colons closes the innermost open directive that was opened with N colons
 * (so `::::cards` wraps `:::card` blocks and is closed by its own `::::`).
 * When no open directive matches the length exactly, the fence falls back to
 * the innermost directive it is long enough to close — which is how a bare
 * `:::` has always behaved.
 */
final class DirectiveBlockParser extends AbstractBlockContinueParser
{
    private DirectiveBlock $block;

    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(string $name, array $attributes, ?string $shorthand, int $fenceLength = 3)
    {
        $this->block = new DirectiveBlock($name, $attributes, $shorthand, $fenceLength);
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

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): BlockContinue
    {
        $fenceLength = $this->closingFenceLength($cursor);

        if ($fenceLength !== null && $this->ownsFence($activeBlockParser, $fenceLength)) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }

    private function closingFenceLength(Cursor $cursor): ?int
    {
        if ($cursor->isIndented() || preg_match('/^(:{3,})\s*$/', $cursor->getRemainder(), $m) !== 1) {
            return null;
        }

        return strlen($m[1]);
    }

    /**
     * Whether this directive owns a closing fence of the given length. The
     * owner is the innermost open directive whose opening fence matches the
     * length exactly, or — when nothing matches exactly — the innermost open
     * directive the fence is at least long enough to close. Outer parsers see
     * each line first, so a claim here implicitly finalizes anything deeper.
     */
    private function ownsFence(BlockContinueParserInterface $activeBlockParser, int $fenceLength): bool
    {
        $open = $this->openDirectives($activeBlockParser);

        foreach ($open as $directive) {
            if ($directive->fenceLength === $fenceLength) {
                return $directive === $this->block;
            }
        }

        foreach ($open as $directive) {
            if ($directive->fenceLength <= $fenceLength) {
                return $directive === $this->block;
            }
        }

        return false;
    }

    /**
     * Every open directive, innermost first, walked up from the active block.
     *
     * @return list<DirectiveBlock>
     */
    private function openDirectives(BlockContinueParserInterface $activeBlockParser): array
    {
        $directives = [];
        $node = $activeBlockParser->getBlock();

        while ($node !== null) {
            if ($node instanceof DirectiveBlock) {
                $directives[] = $node;
            }

            $node = $node->parent();
        }

        return $directives;
    }
}
