<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/** Warns when the configured Phase A context-stuffing budget will omit pages. */
final class AiCorpusSizeCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        if (! config('docent.ai.enabled', false)) {
            return;
        }

        $characters = 0;

        foreach ($context->pages() as $page) {
            if ($page->searchExcluded) {
                continue;
            }

            $characters += strlen($context->source($page->slug)?->rawContent ?? '');
        }

        $tokens = (int) ceil($characters / 4);
        $budget = max(1, (int) config('docent.ai.corpus_budget', 150000));

        if ($tokens > $budget) {
            yield Issue::warning(
                'ai-corpus-large',
                '',
                'The AI corpus is approximately '.number_format($tokens).' tokens, above the '.number_format($budget)
                    .'-token budget. Increase docent.ai.corpus_budget or plan Phase B retrieval; Phase A will omit whole pages from the end.',
            );
        }
    }
}
