<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;
use STS\Docent\Validation\OptInCheck;

final class SingleH1Check implements OptInCheck
{
    public function rule(): string
    {
        return 'single-h1';
    }

    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof Heading && $node->level === 1) {
                    yield Issue::warning(
                        'single-h1',
                        $page->slug,
                        'Body contains an h1; the page title already renders as the h1. Start body headings at h2.',
                        $node->line,
                    );
                }
            }
        }
    }
}
