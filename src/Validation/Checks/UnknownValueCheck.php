<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `{{ value:key }}` tokens whose key is not registered.
 */
final class UnknownValueCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof DynamicValue && $node->key !== '' && ! $context->registry()->hasValue($node->key)) {
                    yield Issue::error('unknown-value', $page->slug, 'Unknown value "'.$node->key.'".', $node->line);
                }
            }
        }
    }
}
