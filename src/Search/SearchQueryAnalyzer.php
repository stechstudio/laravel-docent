<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchQueryAnalyzer
{
    /** @var list<string> */
    private const DEFAULT_STOP_WORDS = [
        'a', 'an', 'and', 'are', 'can', 'could', 'do', 'does', 'for', 'from',
        'how', 'i', 'in', 'is', 'it', 'my', 'of', 'on', 'or', 'our', 'should',
        'that', 'the', 'this', 'to', 'we', 'what', 'when', 'where', 'which',
        'with', 'would', 'you', 'your',
    ];

    /** @var list<string> */
    private array $stopWords;

    /**
     * Stop words come from the site graph (`search.stop_words`, already
     * site-resolved); null keeps the shipped English defaults. Deliberately
     * no config fallback here — a raw global read would bypass per-site
     * overrides.
     *
     * @param  ?list<string>  $stopWords
     */
    public function __construct(?array $stopWords = null)
    {
        $this->stopWords = array_values(array_unique(array_map(
            static fn (mixed $word): string => mb_strtolower(trim((string) $word)),
            $stopWords ?? self::DEFAULT_STOP_WORDS,
        )));
    }

    public function analyze(string $query): SearchQuery
    {
        $all = SearchTokenizer::tokenize($query);
        $meaningful = [];

        foreach ($all as $index => $term) {
            if (! in_array($term, $this->stopWords, true)) {
                $meaningful[] = ['value' => $term, 'index' => $index];
            }
        }

        if ($meaningful === []) {
            $meaningful = array_map(
                static fn (string $term, int $index): array => ['value' => $term, 'index' => $index],
                $all,
                array_keys($all),
            );
        }

        $lastIndex = array_key_last($all);
        $terms = array_map(static fn (array $term): SearchTerm => new SearchTerm(
            value: $term['value'],
            stem: SearchTokenizer::stem($term['value']),
            prefixEligible: $term['index'] === $lastIndex,
        ), $meaningful);

        return new SearchQuery(
            original: $query,
            phrase: implode(' ', array_column($meaningful, 'value')),
            allTerms: $all,
            terms: $terms,
        );
    }
}
