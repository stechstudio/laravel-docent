<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `{{ link:key }}` tokens whose key is not a registered application link.
 */
final class UnknownLinkCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof AppLink && $node->kind === AppLinkKind::Link && ! $context->registry()->hasLink($node->key)) {
                    yield Issue::error('unknown-link', $page->slug, 'Unknown link "'.$node->key.'".', $node->line);
                }
            }
        }
    }
}
