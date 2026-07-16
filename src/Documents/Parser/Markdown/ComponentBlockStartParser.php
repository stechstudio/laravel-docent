<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Recognizes a block-level, self-closing `<docs-component name="x" foo="bar" />`
 * tag that sits alone on a line, before CommonMark treats it as a raw HTML block.
 */
final class ComponentBlockStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $line = trim($cursor->getRemainder());

        if (preg_match('/^<docs-component\b(.*?)\/>$/s', $line, $m) !== 1) {
            return BlockStart::none();
        }

        $parsed = AttributeParser::parse($m[1]);
        $name = $parsed['attributes']['name'] ?? $parsed['shorthand'] ?? '';

        $attributes = $parsed['attributes'];
        unset($attributes['name']);

        $cursor->advanceToEnd();

        return BlockStart::of(new ComponentBlockParser($name, $attributes))->at($cursor);
    }
}
