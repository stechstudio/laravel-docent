<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchQuery
{
    /**
     * @param  list<string>  $allTerms
     * @param  list<SearchTerm>  $terms
     */
    public function __construct(
        public readonly string $original,
        public readonly string $phrase,
        public readonly array $allTerms,
        public readonly array $terms,
    ) {}
}
