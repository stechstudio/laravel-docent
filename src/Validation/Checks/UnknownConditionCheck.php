<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `:::when` / `:::unless` blocks whose condition is not registered.
 */
final class UnknownConditionCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof ConditionBlock && $node->condition !== '' && ! $context->registry()->hasCondition($node->condition)) {
                    yield Issue::error('unknown-condition', $page->slug, 'Unknown condition "'.$node->condition.'".', $node->line);
                }
            }
        }
    }
}
