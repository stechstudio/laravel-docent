<?php

declare(strict_types=1);

namespace STS\Docent\Insights;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use STS\Docent\DocentManager;
use STS\Docent\Insights\Models\InsightEvent;

/**
 * Aggregates insight events in the database so the admin view stays cheap no
 * matter how many raw events the retention window holds.
 */
final class InsightSummary
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    /** @return array<string, mixed> */
    public function forDays(int $days = 30): array
    {
        $days = min(365, max(1, $days));
        $since = now()->subDays($days)->toImmutable();

        $searches = $this->since($since)
            ->where('event', InsightRecorder::SEARCH_SUBMITTED)
            ->whereNotNull('query');
        $outcomes = fn (string $status): Builder => $this->since($since)
            ->where('event', InsightRecorder::ASSISTANT_OUTCOME)
            ->where('status', $status);

        return [
            'days' => $days,
            'since' => $since->toDateString(),
            'totals' => [
                'page_views' => $this->since($since)->where('event', InsightRecorder::PAGE_VIEWED)->count(),
                'searches' => (clone $searches)->count(),
                'search_clicks' => $this->since($since)->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)->count(),
                'assistant_answers' => $outcomes('answered')->count(),
                'assistant_unanswered' => $outcomes('unanswered')->count(),
            ],
            'top_pages' => $this->counts($this->since($since)->where('event', InsightRecorder::PAGE_VIEWED), 'page_slug'),
            'top_searches' => $this->counts(clone $searches, 'query'),
            'low_ctr_searches' => $this->lowCtr(clone $searches, $since),
            'unanswered_questions' => $this->counts($outcomes('unanswered'), 'query'),
            'negative_feedback' => $this->counts(
                $this->since($since)->where('event', InsightRecorder::ASSISTANT_FEEDBACK)->where('feedback', 'down'),
                'query',
            ),
        ];
    }

    /** @return Builder<InsightEvent> */
    private function since(CarbonImmutable $since): Builder
    {
        return InsightEvent::forSite($this->connection(), $this->docent->key())
            ->where('created_at', '>=', $since);
    }

    private function connection(): ?string
    {
        $connection = $this->docent->config('database.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * @param  Builder<InsightEvent>  $events
     * @return list<array{label: string, count: int}>
     */
    private function counts(Builder $events, string $field): array
    {
        return $events
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->select($field.' as label')
            ->selectRaw('count(*) as aggregate')
            ->groupBy($field)
            ->orderByDesc('aggregate')
            ->orderBy('label')
            ->limit(10)
            ->get()
            ->map(static fn (InsightEvent $row): array => [
                'label' => (string) $row->getAttribute('label'),
                'count' => (int) $row->getAttribute('aggregate'),
            ])
            ->all();
    }

    /**
     * Click and no-click interaction rows copy the query from their search, so
     * per-query click counts group directly — no per-event scan required.
     *
     * @param  Builder<InsightEvent>  $searches
     * @return list<array{query: string, searches: int, clicks: int, ctr: float}>
     */
    private function lowCtr(Builder $searches, CarbonImmutable $since): array
    {
        $clicks = $this->since($since)
            ->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)
            ->whereNotNull('query')
            ->selectRaw('count(*) as aggregate')
            ->addSelect('query')
            ->groupBy('query')
            ->pluck('aggregate', 'query');

        return $searches
            ->where('query', '!=', '')
            ->select('query')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('query')
            ->get()
            ->map(static function (InsightEvent $row) use ($clicks): array {
                $query = (string) $row->getAttribute('query');
                $searchCount = (int) $row->getAttribute('aggregate');
                $clickCount = min((int) ($clicks[$query] ?? 0), $searchCount);

                return [
                    'query' => $query,
                    'searches' => $searchCount,
                    'clicks' => $clickCount,
                    'ctr' => $searchCount === 0 ? 0.0 : round(($clickCount / $searchCount) * 100, 1),
                ];
            })
            ->sortBy([['ctr', 'asc'], ['searches', 'desc'], ['query', 'asc']])
            ->take(10)
            ->values()
            ->all();
    }
}
