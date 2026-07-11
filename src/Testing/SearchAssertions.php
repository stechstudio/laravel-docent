<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use PHPUnit\Framework\Assert;
use STS\Docent\Search\SearchResult;

/**
 * Fluent assertions about the results of a real {@see SearchEngine} query,
 * already authorization-filtered for the viewer that ran it.
 */
final class SearchAssertions
{
    /**
     * @param  list<SearchResult>  $results
     */
    public function __construct(
        private readonly string $query,
        private readonly array $results,
    ) {}

    public function assertSees(string $text): self
    {
        Assert::assertTrue(
            $this->contains($text),
            "Expected search for [{$this->query}] to surface [{$text}], but no visible result matched.",
        );

        return $this;
    }

    public function assertMissing(string $text): self
    {
        Assert::assertFalse(
            $this->contains($text),
            "Expected search for [{$this->query}] to NOT surface [{$text}], but a result matched.",
        );

        return $this;
    }

    public function assertEmpty(): self
    {
        Assert::assertSame(
            [],
            $this->results,
            "Expected search for [{$this->query}] to return no results, but it returned ".count($this->results).'.',
        );

        return $this;
    }

    public function assertCount(int $expected): self
    {
        Assert::assertCount($expected, $this->results, "Unexpected result count for search [{$this->query}].");

        return $this;
    }

    private function contains(string $needle): bool
    {
        foreach ($this->results as $result) {
            $haystack = $result->title.' '.$result->slug.' '.html_entity_decode(strip_tags($result->snippet));

            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
