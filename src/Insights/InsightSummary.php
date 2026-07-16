<?php

declare(strict_types=1);

namespace STS\Docent\Insights;

use Illuminate\Support\Collection;
use STS\Docent\Insights\Models\InsightEvent;

final class InsightSummary
{
    /** @return array<string, mixed> */
    public function forDays(int $days = 30): array
    {
        $days = min(365, max(1, $days));
        $events = InsightEvent::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->get();
        $searches = $events->where('event', InsightRecorder::SEARCH_SUBMITTED)->whereNotNull('query');
        $clicked = $events->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)
            ->pluck('search_id')->filter()->flip();
        $assistant = $events->where('event', InsightRecorder::ASSISTANT_OUTCOME);

        return [
            'days' => $days,
            'since' => now()->subDays($days)->toDateString(),
            'totals' => [
                'page_views' => $events->where('event', InsightRecorder::PAGE_VIEWED)->count(),
                'searches' => $searches->count(),
                'search_clicks' => $events->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)->count(),
                'assistant_answers' => $assistant->where('status', 'answered')->count(),
                'assistant_unanswered' => $assistant->where('status', 'unanswered')->count(),
            ],
            'top_pages' => $this->counts($events->where('event', InsightRecorder::PAGE_VIEWED), 'page_slug'),
            'top_searches' => $this->counts($searches, 'query'),
            'low_ctr_searches' => $this->lowCtr($searches, $clicked),
            'unanswered_questions' => $this->counts($assistant->where('status', 'unanswered')->whereNotNull('query'), 'query'),
            'negative_feedback' => $this->counts(
                $events->where('event', InsightRecorder::ASSISTANT_FEEDBACK)->where('feedback', 'down')->whereNotNull('query'),
                'query',
            ),
        ];
    }

    /**
     * @param  Collection<int, InsightEvent>  $events
     * @return list<array{label: string, count: int}>
     */
    private function counts(Collection $events, string $field): array
    {
        return $events
            ->filter(fn (InsightEvent $event): bool => is_string($event->{$field}) && $event->{$field} !== '')
            ->groupBy($field)
            ->map(fn (Collection $group, string $label): array => ['label' => $label, 'count' => $group->count()])
            ->sortBy([['count', 'desc'], ['label', 'asc']])
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, InsightEvent>  $searches
     * @param  Collection<string, int>  $clicked
     * @return list<array{query: string, searches: int, clicks: int, ctr: float}>
     */
    private function lowCtr(Collection $searches, Collection $clicked): array
    {
        return $searches
            ->groupBy('query')
            ->map(function (Collection $group, string $query) use ($clicked): array {
                $searchCount = $group->count();
                $clickCount = $group->filter(
                    fn (InsightEvent $event): bool => $event->search_id !== null && $clicked->has($event->search_id),
                )->count();

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
