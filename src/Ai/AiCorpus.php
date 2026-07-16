<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

/** The permission-pruned, page-bounded corpus supplied to one viewer's asks. */
final readonly class AiCorpus
{
    /**
     * @param  list<array{slug: string, title: string, url: string}>  $citations
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public string $content,
        public array $citations,
        /** Stable across questions for one viewer and documentation version. */
        public string $version,
        /** Specific to the selected sources for this question and history. */
        public string $retrievalVersion,
        public int $estimatedTokens,
        public bool $truncated,
        public int $omittedPages,
        public array $diagnostics = [],
    ) {}
}
