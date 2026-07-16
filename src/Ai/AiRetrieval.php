<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

final readonly class AiRetrieval
{
    /**
     * @param  list<AiRetrievalCandidate>  $candidates
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public array $candidates,
        public array $diagnostics,
    ) {}
}
