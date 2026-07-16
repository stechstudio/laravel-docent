<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\Search\SearchRecord;
use STS\Docent\Search\SearchSection;

final readonly class AiRetrievalCandidate
{
    /** @param list<string> $reasons */
    public function __construct(
        public SearchRecord $record,
        public SearchSection $section,
        public float $score,
        public array $reasons,
    ) {}
}
