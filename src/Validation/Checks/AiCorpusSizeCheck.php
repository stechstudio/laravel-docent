<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/** Warns when retrieval cannot fit a useful documentation excerpt. */
final class AiCorpusSizeCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        if (! config('docent.ai.enabled', false)) {
            return;
        }

        $budget = max(1, (int) config('docent.ai.corpus_budget', 150000));

        if ($budget < 256) {
            yield Issue::warning(
                'ai-corpus-small',
                '',
                'The AI corpus budget is '.number_format($budget)
                    .' tokens. Increase docent.ai.corpus_budget to at least 256 so retrieval can include a useful documentation excerpt.',
            );
        }
    }
}
