<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags unregistered audiences, both in `:::audience` blocks and in a page's
 * `audience` front matter (which gates the whole page).
 */
final class UnknownAudienceCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            if ($page->audience !== null && ! $context->registry()->hasAudience($page->audience)) {
                yield Issue::error('unknown-audience', $page->slug, 'Unknown audience "'.$page->audience.'" in front matter.', 1);
            }

            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof AudienceBlock && $node->audience !== '' && ! $context->registry()->hasAudience($node->audience)) {
                    yield Issue::error('unknown-audience', $page->slug, 'Unknown audience "'.$node->audience.'".', $node->line);
                }
            }
        }
    }
}
