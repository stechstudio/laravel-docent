<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Emphasis;
use STS\Docent\Documents\Ast\HardBreak;
use STS\Docent\Documents\Ast\HtmlInline;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\SoftBreak;
use STS\Docent\Documents\Ast\Strikethrough;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

if (! function_exists('docParse')) {
    /**
     * Parse a markdown string into a Docent document.
     */
    function docParse(string $markdown): Document
    {
        return (new MarkdownDocumentParser)->parse($markdown);
    }

    function docRegistry(?Closure $classResolver = null): IntegrationRegistry
    {
        return new IntegrationRegistry($classResolver);
    }

    function docContext(?Closure $gate = null, ?string $audience = null, array $parameters = []): DocumentationContext
    {
        return new DocumentationContext(parameters: $parameters, audience: $audience, gate: $gate);
    }

    /**
     * Depth-first search for the first node of the given class.
     *
     * @param  class-string  $class
     */
    function docFind(Node $node, string $class): ?Node
    {
        if ($node instanceof $class) {
            return $node;
        }

        foreach ($node->children as $child) {
            if ($found = docFind($child, $class)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Collect every node of the given class.
     *
     * @param  class-string  $class
     * @return list<Node>
     */
    function docFindAll(Node $node, string $class, array &$acc = []): array
    {
        if ($node instanceof $class) {
            $acc[] = $node;
        }

        foreach ($node->children as $child) {
            docFindAll($child, $class, $acc);
        }

        return $acc;
    }

    /**
     * A canonical, comparable form of a node's body that ignores source line
     * numbers and normalizes inline formatting the ProseMirror way — nested
     * emphasis/strong/strike/code wrappers become order-independent mark sets on
     * whitespace-collapsed text runs, and soft breaks become spaces. Two ASTs
     * that render identically canonicalize equal, which is exactly the semantic
     * round-trip promise (never byte-level).
     */
    function docCanonical(Node $node): array
    {
        return [
            'type' => (new ReflectionClass($node))->getShortName(),
            'attrs' => docNodeAttrs($node),
            'children' => docCanonicalChildren($node->children),
        ];
    }

    /**
     * The scalar public attributes of a node (its `children` and `line` aside).
     */
    function docNodeAttrs(Node $node): array
    {
        $attrs = [];

        foreach ((new ReflectionObject($node))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($name === 'children' || $name === 'line') {
                continue;
            }

            $value = $property->getValue($node);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof AppLink) {
                $value = ['appLink', $value->kind->value, $value->key, $value->parameters];
            }

            $attrs[$name] = $value;
        }

        ksort($attrs);

        return $attrs;
    }

    /**
     * @param  list<Node>  $children
     */
    function docCanonicalChildren(array $children): array
    {
        if ($children === []) {
            return [];
        }

        $allInline = array_reduce($children, static fn (bool $carry, Node $child): bool => $carry && docIsInline($child), true);

        if ($allInline) {
            return docFlattenInline($children, []);
        }

        return array_map('docCanonical', $children);
    }

    function docIsInline(Node $node): bool
    {
        return $node instanceof Text || $node instanceof SoftBreak || $node instanceof HardBreak
            || $node instanceof Emphasis || $node instanceof Strong || $node instanceof Strikethrough
            || $node instanceof InlineCode || $node instanceof Link || $node instanceof Image
            || $node instanceof DynamicValue || $node instanceof AppLink || $node instanceof HtmlInline;
    }

    /**
     * @param  list<Node>  $nodes
     * @param  array<string, mixed>  $marks
     */
    function docFlattenInline(array $nodes, array $marks): array
    {
        $tokens = [];

        foreach ($nodes as $node) {
            match (true) {
                $node instanceof Text => $tokens[] = ['text', $node->content, docMarkKey($marks)],
                $node instanceof SoftBreak => $tokens[] = ['text', ' ', docMarkKey($marks)],
                $node instanceof HardBreak => $tokens[] = ['break', docMarkKey($marks)],
                $node instanceof InlineCode => $tokens[] = ['code', $node->code, docMarkKey([...$marks, 'code' => true])],
                $node instanceof Emphasis => $tokens = [...$tokens, ...docFlattenInline($node->children, [...$marks, 'italic' => true])],
                $node instanceof Strong => $tokens = [...$tokens, ...docFlattenInline($node->children, [...$marks, 'bold' => true])],
                $node instanceof Strikethrough => $tokens = [...$tokens, ...docFlattenInline($node->children, [...$marks, 'strike' => true])],
                $node instanceof Link => $tokens = [...$tokens, ...docFlattenInline($node->children, [...$marks, 'link' => docLinkKey($node)])],
                $node instanceof Image => $tokens[] = ['image', $node->url, $node->alt, $node->title, docMarkKey($marks)],
                $node instanceof DynamicValue => $tokens[] = ['value', $node->key, $node->arguments, docMarkKey($marks)],
                $node instanceof AppLink => $tokens[] = ['appLink', $node->kind->value, $node->key, $node->parameters, docMarkKey($marks)],
                $node instanceof HtmlInline => $tokens[] = ['htmlInline', $node->html, docMarkKey($marks)],
                default => null,
            };
        }

        return docMergeInlineRuns($tokens);
    }

    function docLinkKey(Link $node): string
    {
        $destination = $node->destination;

        return $destination instanceof AppLink
            ? 'appLink:'.$destination->kind->value.':'.$destination->key.':'.implode(',', $destination->parameters)
            : $destination;
    }

    /**
     * @param  array<string, mixed>  $marks
     */
    function docMarkKey(array $marks): array
    {
        $keys = array_keys($marks);
        sort($keys);

        return $keys;
    }

    function docMergeInlineRuns(array $tokens): array
    {
        $merged = [];

        foreach ($tokens as $token) {
            $last = $merged === [] ? null : $merged[count($merged) - 1];

            if ($token[0] === 'text' && $last !== null && $last[0] === 'text' && $last[2] === $token[2]) {
                $merged[count($merged) - 1][1] .= $token[1];

                continue;
            }

            $merged[] = $token;
        }

        foreach ($merged as &$token) {
            if ($token[0] === 'text') {
                $token[1] = preg_replace('/\s+/', ' ', $token[1]);
            }
        }

        return $merged;
    }

    /**
     * Whether two documents are semantically equal (body only — front matter is
     * out of band for the Tiptap bridge).
     */
    function docSemanticEquals(Document $a, Document $b): bool
    {
        return docCanonicalChildren($a->children) == docCanonicalChildren($b->children);
    }
}
