<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Detects include cycles (a partial that, directly or transitively, includes
 * itself). Each cycle is reported once per page that reaches it, pointing at the
 * page's own `:::include` that leads in.
 */
final class IncludeCycleCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            /** @var array<string, true> $reported */
            $reported = [];

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof IncludeNode) {
                    yield from $this->descend($context, $page->slug, $node->name, $node->line, [], $reported);
                }
            }
        }
    }

    /**
     * @param  list<string>  $stack
     * @param  array<string, true>  $reported
     * @return iterable<Issue>
     */
    private function descend(CheckContext $context, string $slug, string $name, ?int $originLine, array $stack, array &$reported): iterable
    {
        if (in_array($name, $stack, true)) {
            $offset = array_search($name, $stack, true);
            $cycle = implode(' → ', [...array_slice($stack, (int) $offset), $name]);

            if (! isset($reported[$cycle])) {
                $reported[$cycle] = true;

                yield Issue::error('include-cycle', $slug, 'Include cycle detected: '.$cycle.'.', $originLine);
            }

            return;
        }

        $partial = $context->partial($name);

        if ($partial === null) {
            return;
        }

        $stack[] = $name;

        foreach (AstWalker::walk($partial) as $node) {
            if ($node instanceof IncludeNode) {
                yield from $this->descend($context, $slug, $node->name, $originLine, $stack, $reported);
            }
        }
    }
}
