<?php

declare(strict_types=1);

namespace STS\Docent\Search;

use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;

/** Runs permission-filtered, section-aware ranked lexical search. */
final class SearchEngine
{
    private const MIN_QUERY_LENGTH = 2;

    private const SNIPPET_LENGTH = 160;

    private const K1 = 1.2;

    private const B = 0.75;

    /** @var array<string, float> */
    private const FIELD_WEIGHTS = [
        'title' => 5.0,
        'heading' => 3.5,
        'description' => 2.5,
        'keywords' => 2.0,
        'body' => 1.0,
    ];

    public function __construct(
        private readonly SearchIndexer $indexer,
        private readonly DocentManager $manager,
        private readonly SearchQueryAnalyzer $analyzer = new SearchQueryAnalyzer,
    ) {}

    /** @return list<SearchResult> */
    public function search(string $query, DocumentationContext $context, int $limit = 10): array
    {
        $analyzed = $this->analyzer->analyze($query);

        return array_map(
            fn (SearchMatch $match): SearchResult => $this->result($match->record, $match->section, $analyzed),
            $this->ranked($query, $context, $limit),
        );
    }

    /** @return list<SearchMatch> */
    public function ranked(string $query, DocumentationContext $context, int $limit = 10): array
    {
        if (mb_strlen(trim($query)) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $analyzed = $this->analyzer->analyze($query);

        if ($analyzed->terms === []) {
            return [];
        }

        $index = $this->indexer->index();
        $scored = [];

        foreach ($index->records as $record) {
            if (! $this->manager->authorizes($record->authorize, $record->audience, $context)) {
                continue;
            }

            $match = $this->score($record, $index, $analyzed);
            if ($match !== null) {
                $scored[] = new SearchMatch($match['record'], $match['section'], $match['score']);
            }
        }

        usort($scored, static function (SearchMatch $left, SearchMatch $right): int {
            return ($right->score <=> $left->score)
                ?: ($left->record->order <=> $right->record->order)
                ?: strcmp($left->record->slug, $right->record->slug);
        });

        return array_slice($scored, 0, max(1, $limit));
    }

    /** @return array{record: SearchRecord, section: SearchSection, score: float}|null */
    private function score(SearchRecord $record, SearchIndex $index, SearchQuery $query): ?array
    {
        $globalFields = [
            'title' => $record->titleTokens,
            'description' => $record->descriptionTokens,
            'keywords' => $record->keywordTokens,
        ];
        $globalText = [
            'title' => $record->title,
            'description' => (string) $record->description,
            'keywords' => implode(' ', $record->keywords),
        ];

        $best = null;

        foreach ($record->sections as $section) {
            $score = 0.0;
            $matched = [];

            foreach ($query->terms as $position => $term) {
                $termScore = 0.0;
                $termMatched = false;

                foreach ($globalFields as $field => $tokens) {
                    [$fieldScore, $fieldMatched] = $this->fieldScore($tokens, $field, $term, $index);
                    $termScore += $fieldScore;
                    $termMatched = $termMatched || $fieldMatched;
                }

                foreach (['heading' => $section->headingTokens, 'body' => $section->bodyTokens] as $field => $tokens) {
                    [$fieldScore, $fieldMatched] = $this->fieldScore($tokens, $field, $term, $index);
                    $termScore += $fieldScore;
                    $termMatched = $termMatched || $fieldMatched;
                }

                $score += $termScore;
                $matched[$position] = $termMatched;
            }

            $coverage = count(array_filter($matched)) / max(1, count($query->terms));
            if ($coverage <= 0) {
                continue;
            }

            $score *= 0.4 + (1.6 * ($coverage ** 2));
            $score += $this->phraseBonus($query->phrase, $globalText, $section);

            if ($best === null || $score > $best['score']
                || ($score === $best['score'] && $section->order < $best['section']->order)) {
                $best = ['record' => $record, 'section' => $section, 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * @param  list<string>  $tokens
     * @return array{float, bool}
     */
    private function fieldScore(array $tokens, string $field, SearchTerm $term, SearchIndex $index): array
    {
        if ($tokens === []) {
            return [0.0, false];
        }

        $factor = 0.0;
        $occurrences = 0;

        foreach ($tokens as $candidate) {
            $candidateFactor = $this->matchFactor($candidate, $term);
            if ($candidateFactor <= 0.0) {
                continue;
            }

            $factor = max($factor, $candidateFactor);
            $occurrences++;
        }

        if ($occurrences === 0) {
            return [0.0, false];
        }

        $documents = max(1, count($index->records));
        $frequency = $index->documentFrequencies[$term->stem] ?? 0;
        $idf = log(1 + (($documents - $frequency + 0.5) / ($frequency + 0.5)));
        $length = count($tokens);
        $average = max(1.0, (float) ($index->averageFieldLengths[$field] ?? 1.0));
        $normalized = ($occurrences * (self::K1 + 1))
            / ($occurrences + self::K1 * (1 - self::B + self::B * ($length / $average)));

        return [self::FIELD_WEIGHTS[$field] * $idf * $normalized * $factor, true];
    }

    private function matchFactor(string $candidate, SearchTerm $term): float
    {
        if ($candidate === $term->value) {
            return 1.0;
        }

        if (SearchTokenizer::stem($candidate) === $term->stem) {
            return 0.9;
        }

        if ($term->prefixEligible && mb_strlen($term->value) >= 2 && str_starts_with($candidate, $term->value)) {
            return 0.72;
        }

        if (mb_strlen($term->value) >= 5 && abs(mb_strlen($candidate) - mb_strlen($term->value)) <= 1
            && $this->withinOneEdit($candidate, $term->value)) {
            return 0.52;
        }

        return 0.0;
    }

    /** @param array<string, string> $global */
    private function phraseBonus(string $phrase, array $global, SearchSection $section): float
    {
        if ($phrase === '') {
            return 0.0;
        }

        foreach (['title' => 4.0, 'description' => 2.0, 'keywords' => 2.5] as $field => $bonus) {
            if (str_contains(mb_strtolower($global[$field]), $phrase)) {
                return $bonus;
            }
        }

        if ($section->title !== null && str_contains(mb_strtolower($section->title), $phrase)) {
            return 3.0;
        }

        return str_contains(mb_strtolower($section->body), $phrase) ? 1.0 : 0.0;
    }

    private function result(SearchRecord $record, SearchSection $section, SearchQuery $query): SearchResult
    {
        $snippetBody = trim($section->body) !== '' ? $section->body : $record->body;

        return new SearchResult(
            slug: $record->slug,
            url: $this->manager->url($record->slug),
            title: $record->title,
            group: $record->group,
            snippet: $this->snippet($snippetBody, $query),
            heading: $section->title,
            anchor: $section->slug,
        );
    }

    private function snippet(string $body, SearchQuery $query): string
    {
        if (trim($body) === '' || preg_match_all('/\w+/u', $body, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return '';
        }

        $words = $matches[0];
        $center = 0;

        foreach ($words as $index => [$word]) {
            if ($this->wordMatches($word, $query)) {
                $center = $index;
                break;
            }
        }

        [$from, $to] = $this->window($words, $center, strlen($body));
        $slice = substr($body, $from, $to - $from);

        return ($from > 0 ? '…' : '').$this->highlight($slice, $query).($to < strlen($body) ? '…' : '');
    }

    /**
     * @param  list<array{0: string, 1: int}>  $words
     * @return array{int, int}
     */
    private function window(array $words, int $center, int $length): array
    {
        $start = $center;
        $end = $center;
        $span = strlen($words[$center][0]);

        while ($span < self::SNIPPET_LENGTH) {
            $grew = false;
            if ($start > 0) {
                $start--;
                $span = $this->wordEnd($words[$end]) - $words[$start][1];
                $grew = true;
            }
            if ($span < self::SNIPPET_LENGTH && $end < count($words) - 1) {
                $end++;
                $span = $this->wordEnd($words[$end]) - $words[$start][1];
                $grew = true;
            }
            if (! $grew) {
                break;
            }
        }

        return [$words[$start][1], min($this->wordEnd($words[$end]), $length)];
    }

    private function highlight(string $slice, SearchQuery $query): string
    {
        if (preg_match_all('/\w+/u', $slice, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return e($slice);
        }

        $output = '';
        $cursor = 0;

        foreach ($matches[0] as [$word, $offset]) {
            if (! $this->wordMatches($word, $query)) {
                continue;
            }
            $output .= e(substr($slice, $cursor, $offset - $cursor)).'<mark>'.e($word).'</mark>';
            $cursor = $offset + strlen($word);
        }

        return $output.e(substr($slice, $cursor));
    }

    private function wordMatches(string $word, SearchQuery $query): bool
    {
        $candidate = mb_strtolower($word);
        foreach ($query->terms as $term) {
            if ($this->matchFactor($candidate, $term) > 0.0) {
                return true;
            }
        }

        return false;
    }

    /** @param array{0: string, 1: int} $word */
    private function wordEnd(array $word): int
    {
        return $word[1] + strlen($word[0]);
    }

    private function withinOneEdit(string $left, string $right): bool
    {
        $a = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lengthA = count($a);
        $lengthB = count($b);

        if (abs($lengthA - $lengthB) > 1) {
            return false;
        }

        $indexA = 0;
        $indexB = 0;
        $edits = 0;

        while ($indexA < $lengthA && $indexB < $lengthB) {
            if ($a[$indexA] === $b[$indexB]) {
                $indexA++;
                $indexB++;

                continue;
            }

            if (++$edits > 1) {
                return false;
            }

            if ($lengthA > $lengthB) {
                $indexA++;
            } elseif ($lengthB > $lengthA) {
                $indexB++;
            } else {
                $indexA++;
                $indexB++;
            }
        }

        return $edits + (($indexA < $lengthA || $indexB < $lengthB) ? 1 : 0) <= 1;
    }
}
