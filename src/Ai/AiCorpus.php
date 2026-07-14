<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

/** The permission-pruned, page-bounded corpus supplied to one viewer's asks. */
final readonly class AiCorpus
{
    /**
     * @param  list<array{slug: string, title: string, url: string}>  $citations
     */
    public function __construct(
        public string $content,
        public array $citations,
        public string $version,
        public int $estimatedTokens,
        public bool $truncated,
        public int $omittedPages,
    ) {}
}
