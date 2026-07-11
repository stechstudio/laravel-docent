<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `<docs-component name="x" />` tags whose name is not registered.
 */
final class UnknownComponentCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof ComponentNode && $node->name !== '' && ! $context->registry()->hasComponent($node->name)) {
                    yield Issue::error('unknown-component', $page->slug, 'Unknown component "'.$node->name.'".', $node->line);
                }
            }
        }
    }
}
