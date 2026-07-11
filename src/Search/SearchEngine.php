<?php

declare(strict_types=1);

namespace STS\Docent\Search;

use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;

/**
 * Runs queries against the cached index. Authorization is applied first — records
 * are dropped for viewers who could not open the underlying page — so a hit can
 * never reveal a gated page's title, slug, or snippet. Only then are the
 * remaining records scored, ranked, and turned into {@see SearchResult}s.
 *
 * Scoring: query tokens are matched case-insensitively with prefix semantics
 * against each field. Field weights are title 5, headings 3, description 2,
 * body 1; a multi-token query requires every token to match somewhere (AND),
 * and an exact-phrase occurrence adds a small bonus (title over body).
 */
final class SearchEngine
{
    private const MIN_QUERY_LENGTH = 2;

    private const WEIGHT_TITLE = 5;

    private const WEIGHT_HEADING = 3;

    private const WEIGHT_DESCRIPTION = 2;

    private const WEIGHT_BODY = 1;

    private const SNIPPET_LENGTH = 160;

    public function __construct(
        private readonly SearchIndexer $indexer,
        private readonly DocentManager $manager,
    ) {}

    /**
     * @return list<SearchResult>
     */
    public function search(string $query, DocumentationContext $context, int $limit = 10): array
    {
        if (mb_strlen(trim($query)) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $tokens = array_values(array_unique($this->tokenize($query)));

        if ($tokens === []) {
            return [];
        }

        $scored = [];

        foreach ($this->indexer->records() as $record) {
            if (! $this->manager->authorizes($record->authorize, $record->audience, $context)) {
                continue;
            }

            $match = $this->score($record, $tokens, $query);

            if ($match !== null) {
                $scored[] = $match;
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            fn (array $match): SearchResult => $this->result($match['record'], $tokens, $match['heading']),
            array_slice($scored, 0, $limit),
        );
    }

    /**
     * Score a record, or return null when the AND semantics fail (some query
     * token matches no field on this record).
     *
     * @param  list<string>  $tokens
     * @return array{record: SearchRecord, score: float, heading: ?string}|null
     */
    private function score(SearchRecord $record, array $tokens, string $query): ?array
    {
        $titleTokens = $this->tokenize($record->title);
        $descriptionTokens = $this->tokenize((string) $record->description);
        $bodyTokens = $this->tokenize($record->body);

        $score = 0.0;
        $heading = null;

        foreach ($tokens as $token) {
            $matched = false;

            if ($this->prefixPresent($titleTokens, $token)) {
                $score += self::WEIGHT_TITLE;
                $matched = true;
            }

            $headingSlug = $this->headingMatch($record->headings, $token);

            if ($headingSlug !== null) {
                $score += self::WEIGHT_HEADING;
                $heading ??= $headingSlug;
                $matched = true;
            }

            if ($this->prefixPresent($descriptionTokens, $token)) {
                $score += self::WEIGHT_DESCRIPTION;
                $matched = true;
            }

            $occurrences = $this->prefixCount($bodyTokens, $token);

            if ($occurrences > 0) {
                // Presence carries the field weight; extra hits add only a small,
                // capped bonus so a verbose body can never outrank a title match.
                $score += self::WEIGHT_BODY + min($occurrences - 1, 9) * 0.1;
                $matched = true;
            }

            if (! $matched) {
                return null;
            }
        }

        $score += $this->phraseBonus($record, $query);

        return ['record' => $record, 'score' => $score, 'heading' => $heading];
    }

    private function phraseBonus(SearchRecord $record, string $query): float
    {
        $phrase = mb_strtolower(trim($query));

        if (str_contains(mb_strtolower($record->title), $phrase)) {
            return 3.0;
        }

        if (str_contains(mb_strtolower($record->body), $phrase)) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function result(SearchRecord $record, array $tokens, ?string $heading): SearchResult
    {
        return new SearchResult(
            slug: $record->slug,
            url: $this->manager->url($record->slug),
            title: $record->title,
            group: $record->group,
            snippet: $this->snippet($record->body, $tokens),
            heading: $heading,
        );
    }

    /**
     * Build a ~160-char window of the leak-safe body centered on the first match,
     * escape it, and wrap matched words in `<mark>`. Escaping happens before the
     * `<mark>` tags are inserted, so no indexed text can inject markup.
     *
     * @param  list<string>  $tokens
     */
    private function snippet(string $body, array $tokens): string
    {
        if (trim($body) === '') {
            return '';
        }

        if (preg_match_all('/\w+/u', $body, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return '';
        }

        /** @var list<array{0: string, 1: int}> $words */
        $words = $matches[0];

        $center = 0;

        foreach ($words as $index => [$word]) {
            if ($this->wordMatches($word, $tokens)) {
                $center = $index;

                break;
            }
        }

        [$from, $to] = $this->window($words, $center, strlen($body));

        $slice = substr($body, $from, $to - $from);

        return ($from > 0 ? '…' : '').$this->highlight($slice, $tokens).($to < strlen($body) ? '…' : '');
    }

    /**
     * Grow a window outward from the centered word until it spans roughly
     * SNIPPET_LENGTH characters, snapping to word boundaries so no multibyte
     * character is split.
     *
     * @param  list<array{0: string, 1: int}>  $words
     * @return array{0: int, 1: int}
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

    /**
     * @param  list<string>  $tokens
     */
    private function highlight(string $slice, array $tokens): string
    {
        if (preg_match_all('/\w+/u', $slice, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return e($slice);
        }

        $output = '';
        $cursor = 0;

        foreach ($matches[0] as [$word, $offset]) {
            if (! $this->wordMatches($word, $tokens)) {
                continue;
            }

            $output .= e(substr($slice, $cursor, $offset - $cursor)).'<mark>'.e($word).'</mark>';
            $cursor = $offset + strlen($word);
        }

        return $output.e(substr($slice, $cursor));
    }

    /**
     * @param  array{0: string, 1: int}  $word
     */
    private function wordEnd(array $word): int
    {
        return $word[1] + strlen($word[0]);
    }

    /**
     * @param  list<array{title: string, slug: string}>  $headings
     */
    private function headingMatch(array $headings, string $token): ?string
    {
        foreach ($headings as $heading) {
            if ($this->prefixPresent($this->tokenize($heading['title']), $token)) {
                return $heading['slug'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function wordMatches(string $word, array $tokens): bool
    {
        $lower = mb_strtolower($word);

        foreach ($tokens as $token) {
            if (str_starts_with($lower, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $haystack
     */
    private function prefixPresent(array $haystack, string $needle): bool
    {
        foreach ($haystack as $token) {
            if (str_starts_with($token, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $haystack
     */
    private function prefixCount(array $haystack, string $needle): int
    {
        $count = 0;

        foreach ($haystack as $token) {
            if (str_starts_with($token, $needle)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        if ($text === '' || preg_match_all('/\w+/u', mb_strtolower($text), $matches) === 0) {
            return [];
        }

        return $matches[0];
    }
}
