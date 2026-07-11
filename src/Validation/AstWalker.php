<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\Node;

/**
 * Depth-first pre-order traversal of a Docent AST. Link destinations authored as
 * `{{ link:... }}` / `{{ route:... }}` live on the `Link` node rather than in
 * its children, so they are surfaced explicitly here.
 */
final class AstWalker
{
    /**
     * @return iterable<Node>
     */
    public static function walk(Node $node): iterable
    {
        yield $node;

        if ($node instanceof Link && $node->destination instanceof AppLink) {
            yield from self::walk($node->destination);
        }

        foreach ($node->children as $child) {
            yield from self::walk($child);
        }
    }
}
