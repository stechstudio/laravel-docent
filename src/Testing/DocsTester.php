<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use STS\Docent\DocentManager;
use STS\Docent\Search\SearchEngine;

/**
 * Fluent factory for documentation test assertions. Reached via
 * {@see InteractsWithDocs::docs()}.
 */
final class DocsTester
{
    use BuildsTestContext;

    public function __construct(
        private readonly DocentManager $manager,
    ) {}

    public function page(string $slug): PageAssertions
    {
        return new PageAssertions($this->manager, $slug);
    }

    public function search(string $query, ?Authenticatable $as = null, ?string $audience = null, int $limit = 20): SearchAssertions
    {
        $results = app(SearchEngine::class)->search($query, $this->testContext($as, $audience), $limit);

        return new SearchAssertions($query, $results);
    }
}
