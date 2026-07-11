<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Closure;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\HardBreak;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\SoftBreak;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Document;

/**
 * Produces leak-safe plain text for the search index.
 *
 * AuthorizationBlock / ConditionBlock / AudienceBlock subtrees are skipped
 * entirely — nothing behind a permission or condition ever reaches the index.
 * DynamicValues and components emit nothing; includes are resolved (they are
 * static content), but any conditional blocks inside a resolved include are
 * still skipped.
 */
final class SearchTextRenderer
{
    private const MAX_INCLUDE_DEPTH = 10;

    /** @var list<string> */
    private array $includeStack = [];

    /**
     * @param  ?Closure(string): ?Document  $includeResolver
     */
    public function __construct(
        private readonly ?Closure $includeResolver = null,
    ) {}

    public function render(Node $node): string
    {
        return $this->normalize($this->renderChildren($node));
    }

    private function renderChildren(Node $node): string
    {
        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->renderNode($child);
        }

        return $text;
    }

    private function renderNode(Node $node): string
    {
        return match (true) {
            // Leak-safe: conditional subtrees never contribute to the index.
            $node instanceof AuthorizationBlock,
            $node instanceof ConditionBlock,
            $node instanceof AudienceBlock => '',

            $node instanceof Heading,
            $node instanceof Paragraph,
            $node instanceof Callout => $this->renderChildren($node)."\n\n",

            $node instanceof CodeBlock => $node->code."\n\n",
            $node instanceof ListItem => $this->renderChildren($node)."\n",
            $node instanceof TableCell => $this->renderChildren($node).' ',
            $node instanceof ThematicBreak => "\n",
            $node instanceof BlockQuote => $this->renderChildren($node),

            $node instanceof Text => $node->content,
            $node instanceof InlineCode => $node->code,
            $node instanceof Image => $node->alt,
            $node instanceof SoftBreak,
            $node instanceof HardBreak => ' ',

            $node instanceof IncludeNode => $this->renderInclude($node),

            // BulletList, OrderedList, Table, TableSection, TableRow, Emphasis,
            // Strong, Strikethrough, Link — just recurse into children.
            // DynamicValue, AppLink, ComponentNode, HtmlBlock, HtmlInline — nothing.
            default => $node->hasChildren() ? $this->renderChildren($node) : '',
        };
    }

    private function renderInclude(IncludeNode $node): string
    {
        if ($this->includeResolver === null
            || in_array($node->name, $this->includeStack, true)
            || count($this->includeStack) >= self::MAX_INCLUDE_DEPTH) {
            return '';
        }

        $document = ($this->includeResolver)($node->name);

        if ($document === null) {
            return '';
        }

        $this->includeStack[] = $node->name;
        $text = $this->renderChildren($document);
        array_pop($this->includeStack);

        return $text;
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{2,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
