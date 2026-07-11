<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Document;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * Builds a nested table of contents from a document's headings. Context-aware:
 * headings inside conditional blocks are included only when the given viewer
 * would see the block. Without a registry/context, conditional subtrees are
 * skipped entirely — the safe default, mirroring the search indexer.
 */
final class TableOfContents
{
    use ResolvesVisibility;

    public function __construct(
        private readonly ?IntegrationRegistry $registry = null,
        private readonly ?DocumentationContext $context = null,
    ) {}

    /**
     * @return list<TocEntry> Tree of entries between $minLevel and $maxDepth (inclusive).
     */
    public static function build(Document $document, int $minLevel = 2, int $maxDepth = 3): array
    {
        return (new self)->buildFor($document, $minLevel, $maxDepth);
    }

    /**
     * @return list<TocEntry>
     */
    public function buildFor(Document $document, int $minLevel = 2, int $maxDepth = 3): array
    {
        /** @var list<TocEntry> $roots */
        $roots = [];
        /** @var list<TocEntry> $stack */
        $stack = [];

        foreach ($this->headings($document) as $heading) {
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
    private function headings(Node $node): array
    {
        $headings = [];

        foreach ($node->children as $child) {
            if ($child instanceof Heading) {
                $headings[] = $child;

                continue;
            }

            if (! $this->visible($child)) {
                continue;
            }

            $headings = [...$headings, ...$this->headings($child)];
        }

        return $headings;
    }

    /**
     * Whether to descend into a node while collecting headings. Conditional
     * blocks require a viewer who passes the same visibility checks the HTML
     * renderer applies; with no context they are always opaque.
     */
    private function visible(Node $node): bool
    {
        $conditional = $node instanceof AuthorizationBlock
            || $node instanceof ConditionBlock
            || $node instanceof AudienceBlock;

        if (! $conditional) {
            return true;
        }

        if ($this->registry === null || $this->context === null) {
            return false;
        }

        return match (true) {
            $node instanceof AuthorizationBlock => $this->authorizationVisible($node, $this->context),
            $node instanceof ConditionBlock => $this->conditionVisible($node, $this->registry, $this->context),
            $node instanceof AudienceBlock => $this->audienceVisible($node, $this->registry, $this->context),
        };
    }
}
