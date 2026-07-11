<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use STS\Docent\Documents\Parser\Markdown\Node\DocentTokenInline;

/**
 * Parses standalone `{{ value:key }}`, `{{ link:key }}`, and `{{ route:name }}`
 * tokens appearing in inline text.
 */
final class TokenInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(TokenSyntax::PARTIAL);
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $matches = $inlineContext->getMatches();

        $node = TokenSyntax::fromMatch($matches[1], $matches[2], $matches[3] ?? '');

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new DocentTokenInline($node));

        return true;
    }
}
