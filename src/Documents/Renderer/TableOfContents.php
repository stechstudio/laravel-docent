<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Document;

/**
 * Builds a nested table of contents from a document's headings.
 */
final class TableOfContents
{
    /**
     * @return list<TocEntry> Tree of entries between level 2 and $maxDepth (inclusive).
     */
    public static function build(Document $document, int $minLevel = 2, int $maxDepth = 3): array
    {
        /** @var list<TocEntry> $roots */
        $roots = [];
        /** @var list<TocEntry> $stack */
        $stack = [];

        foreach (self::headings($document) as $heading) {
            if ($heading->level < $minLevel || $heading->level > $maxDepth) {
                continue;
            }

            $entry = new TocEntry(trim(NodeText::extract($heading)), $heading->slug, $heading->level);

            // Pop until the top of the stack is a shallower heading.
            while ($stack !== [] && end($stack)->level >= $heading->level) {
                array_pop($stack);
            }

            if ($stack === []) {
                $roots[] = $entry;
            } else {
                $parent = end($stack);
                $parent->children[] = $entry;
            }

            $stack[] = $entry;
        }

        return $roots;
    }

    /**
     * @return list<Heading>
     */
    private static function headings(Node $node): array
    {
        $headings = [];

        foreach ($node->children as $child) {
            if ($child instanceof Heading) {
                $headings[] = $child;
            }

            // Headings live at the top level of a document; no need to recurse
            // into conditional blocks for a global TOC.
        }

        return $headings;
    }
}
