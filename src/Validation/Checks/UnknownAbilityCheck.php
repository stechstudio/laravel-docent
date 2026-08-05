<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags ability names the application does not recognize — in a page's
 * `authorize` front matter and in `:::can` / `:::cannot` blocks. A warning, not
 * an error: gates and policies may be registered at runtime and so cannot be
 * proven absent statically.
 *
 * What counts as recognized is the application's declared ability surface when
 * it has one, and `Gate::has()` otherwise. The message stays neutral about which
 * of the two answered, because a declared surface replaces the gate list rather
 * than adding to it — an ability can be perfectly well defined as a gate and
 * still be absent from the surface, and reporting that as "no gate defines it"
 * would be false.
 */
final class UnknownAbilityCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            if ($page->authorize !== null && ! $context->abilityExists($page->authorize)) {
                yield Issue::warning('unknown-ability', $page->slug, 'Unknown ability "'.$page->authorize.'" (front matter `authorize`).', 1);
            }

            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof AuthorizationBlock && $node->ability !== '' && ! $context->abilityExists($node->ability)) {
                    yield Issue::warning('unknown-ability', $page->slug, 'Unknown ability "'.$node->ability.'".', $node->line);
                }
            }
        }
    }
}
