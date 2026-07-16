<?php

declare(strict_types=1);

namespace STS\Docent\Search;

/** A scored page/section match before it is adapted for a specific surface. */
final readonly class SearchMatch
{
    public function __construct(
        public SearchRecord $record,
        public SearchSection $section,
        public float $score,
    ) {}
}
