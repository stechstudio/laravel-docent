<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchIndex
{
    /**
     * @param  list<SearchRecord>  $records
     * @param  array<string, int>  $documentFrequencies
     * @param  array<string, float>  $averageFieldLengths
     */
    public function __construct(
        public readonly array $records,
        public readonly array $documentFrequencies,
        public readonly array $averageFieldLengths,
    ) {}
}
