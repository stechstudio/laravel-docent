<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes the opening line of a container directive (`:::name ...`).
 * Closing fences are handled by {@see DirectiveBlockParser}.
 */
final class DirectiveBlockStartParser implements BlockStartParserInterface
{
    /**
     * Recognized directive names. Anything else is left to normal Markdown parsing.
     *
     * @var list<string>
     */
    public const NAMES = [
        'can', 'cannot', 'when', 'unless', 'audience', 'include',
        'note', 'tip', 'info', 'warning', 'danger',
    ];

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $line = $cursor->getRemainder();

        if (preg_match('/^:{3,}\s*([A-Za-z][A-Za-z0-9_-]*)(.*)$/', $line, $m) !== 1) {
            return BlockStart::none();
        }

        $name = strtolower($m[1]);

        if (! in_array($name, self::NAMES, true)) {
            return BlockStart::none();
        }

        $parsed = AttributeParser::parse($m[2] ?? '');

        $cursor->advanceToEnd();

        if ($name === 'include') {
            $includeName = $parsed['attributes']['name'] ?? $parsed['shorthand'] ?? '';

            return BlockStart::of(new IncludeDirectiveParser($includeName))->at($cursor);
        }

        return BlockStart::of(
            new DirectiveBlockParser($name, $parsed['attributes'], $parsed['shorthand'])
        )->at($cursor);
    }
}
