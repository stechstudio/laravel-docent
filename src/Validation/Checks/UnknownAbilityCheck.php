<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags gate/ability names that have no matching Gate definition — in a page's
 * `authorize` front matter and in `:::can` / `:::cannot` blocks. A warning, not
 * an error: gates and policies may be registered at runtime and so cannot be
 * proven absent statically.
 */
final class UnknownAbilityCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            if ($page->authorize !== null && ! $context->abilityExists($page->authorize)) {
                yield Issue::warning('unknown-ability', $page->slug, 'No Gate/policy defines ability "'.$page->authorize.'" (front matter `authorize`).', 1);
            }

            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof AuthorizationBlock && $node->ability !== '' && ! $context->abilityExists($node->ability)) {
                    yield Issue::warning('unknown-ability', $page->slug, 'No Gate/policy defines ability "'.$node->ability.'".', $node->line);
                }
            }
        }
    }
}
