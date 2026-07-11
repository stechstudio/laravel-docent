<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\Text;

/**
 * Extracts the literal text of an inline subtree (for slugs, headings, alt text).
 */
final class NodeText
{
    public static function extract(Node $node): string
    {
        $text = '';

        foreach ($node->children as $child) {
            $text .= match (true) {
                $child instanceof Text => $child->content,
                $child instanceof InlineCode => $child->code,
                default => self::extract($child),
            };
        }

        return $text;
    }
}
