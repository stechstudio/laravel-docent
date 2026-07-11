<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Card;
use STS\Docent\Support\Icon;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `:::card icon="..."` names that are not in the built-in icon set. A
 * warning, not an error: the card still renders (without an icon), so a typo is
 * worth surfacing without failing a build.
 */
final class UnknownIconCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof Card && $node->icon !== null && $node->icon !== '' && ! Icon::has($node->icon)) {
                    yield Issue::warning('unknown-icon', $page->slug, 'Unknown card icon "'.$node->icon.'".', $node->line);
                }
            }
        }
    }
}
