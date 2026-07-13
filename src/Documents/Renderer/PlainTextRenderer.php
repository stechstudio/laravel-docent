<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Closure;
use STS\Docent\Documents\Ast\Accordion;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Frame;
use STS\Docent\Documents\Ast\HardBreak;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\SoftBreak;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Document;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * Context-aware plain-text rendering: emits exactly what the given context is
 * allowed to see, as text (used for snippets and testing helpers).
 */
final class PlainTextRenderer
{
    use ResolvesVisibility;

    private const MAX_INCLUDE_DEPTH = 10;

    /** @var list<string> */
    private array $includeStack = [];

    /**
     * @param  ?Closure(string): ?Document  $includeResolver
     */
    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly DocumentationContext $context,
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
            $node instanceof AuthorizationBlock => $this->authorizationVisible($node, $this->context) ? $this->renderChildren($node) : '',
            $node instanceof ConditionBlock => $this->conditionVisible($node, $this->registry, $this->context) ? $this->renderChildren($node) : '',
            $node instanceof AudienceBlock => $this->audienceVisible($node, $this->registry, $this->context) ? $this->renderChildren($node) : '',

            $node instanceof Heading,
            $node instanceof Paragraph,
            $node instanceof Callout => $this->renderChildren($node)."\n\n",

            $node instanceof Card => ($node->title ?? '')."\n".$this->renderChildren($node)."\n\n",
            $node instanceof Step => $node->title."\n".$this->renderChildren($node)."\n\n",
            $node instanceof Accordion => $node->title."\n".$this->renderChildren($node)."\n\n",
            $node instanceof Tab => $node->label."\n".$this->renderChildren($node)."\n\n",
            $node instanceof Frame => ($node->caption ?? '')."\n".$this->renderChildren($node)."\n\n",

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
            $node instanceof DynamicValue => $this->registry->resolveValue($node->key, $this->context, $node->arguments) ?? '',
            $node instanceof AppLink => '',

            $node instanceof IncludeNode => $this->renderInclude($node),

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
