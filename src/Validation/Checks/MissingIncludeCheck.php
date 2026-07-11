<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Document;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `:::include` directives that resolve to no `_partials/` file, following
 * includes nested inside partials too. A visited set keeps cyclic partials from
 * recursing forever (cycles themselves are reported by {@see IncludeCycleCheck}).
 */
final class MissingIncludeCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            $visited = [];

            yield from $this->scan($context, $page->slug, $document, $visited);
        }
    }

    /**
     * @param  list<string>  $visited
     * @return iterable<Issue>
     */
    private function scan(CheckContext $context, string $slug, Document $document, array &$visited): iterable
    {
        foreach (AstWalker::walk($document) as $node) {
            if (! $node instanceof IncludeNode) {
                continue;
            }

            if (! $context->hasPartial($node->name)) {
                yield Issue::error('missing-include', $slug, 'Missing include partial "'.$node->name.'".', $node->line);

                continue;
            }

            if (in_array($node->name, $visited, true)) {
                continue;
            }

            $visited[] = $node->name;
            $partial = $context->partial($node->name);

            if ($partial !== null) {
                yield from $this->scan($context, $slug, $partial, $visited);
            }
        }
    }
}
