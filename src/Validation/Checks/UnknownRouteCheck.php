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
 * Flags `{{ route:name }}` tokens whose name is not a registered named route.
 */
final class UnknownRouteCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof AppLink && $node->kind === AppLinkKind::Route && ! $context->routeExists($node->key)) {
                    yield Issue::error('unknown-route', $page->slug, 'Unknown route "'.$node->key.'".', $node->line);
                }
            }
        }
    }
}
