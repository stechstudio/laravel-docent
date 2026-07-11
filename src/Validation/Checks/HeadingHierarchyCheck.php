<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags heading levels that skip a rank (e.g. an h2 followed by an h4), which
 * breaks the document outline and accessibility. A warning, not an error.
 */
final class HeadingHierarchyCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            $previous = null;

            foreach (AstWalker::walk($document) as $node) {
                if (! $node instanceof Heading) {
                    continue;
                }

                if ($previous !== null && $node->level > $previous + 1) {
                    yield Issue::warning(
                        'heading-hierarchy',
                        $page->slug,
                        'Heading level jumps from h'.$previous.' to h'.$node->level.'; add an intermediate heading.',
                        $node->line,
                    );
                }

                $previous = $node->level;
            }
        }
    }
}
