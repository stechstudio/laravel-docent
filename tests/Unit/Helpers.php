<?php

declare(strict_types=1);

use Closure;
use STS\Docent\Documents\Ast\Node;
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
}
