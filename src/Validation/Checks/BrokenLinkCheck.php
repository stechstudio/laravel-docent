<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Link;
use STS\Docent\Support\InternalLink;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags slug-style internal markdown links whose destination matches no known
 * page. "Internal" mirrors the HtmlRenderer's url resolver: absolute URLs,
 * protocol-relative, `mailto:`/`tel:`, and pure anchors are external and
 * skipped; so are absolute paths outside the docs route prefix. Anchor-only
 * links are out of scope for v1.
 */
final class BrokenLinkCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        $known = $context->slugSet();

        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if (! $node instanceof Link || ! is_string($node->destination)) {
                    continue;
                }

                $target = InternalLink::resolve($node->destination, $page->directory, $context->routePrefix());

                if ($target === null || isset($known[$target['slug']])) {
                    continue;
                }

                yield Issue::error(
                    'broken-link',
                    $page->slug,
                    'Link to "'.$node->destination.'" matches no known page.',
                    $node->line,
                );
            }
        }
    }
}
