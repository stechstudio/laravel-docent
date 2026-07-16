<?php

declare(strict_types=1);

namespace STS\Docent\Insights;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use STS\Docent\Ai\AiCorpus;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\Insights\Models\InsightEvent;
use STS\Docent\Search\SearchResult;

/** Records a fixed, identity-free event schema for improving human-facing help. */
final class InsightRecorder
{
    public const PAGE_VIEWED = 'page_viewed';

    public const SEARCH_SUBMITTED = 'search_submitted';

    public const SEARCH_RESULTS_IMPRESSED = 'search_results_impressed';

    public const SEARCH_RESULT_CLICKED = 'search_result_clicked';

    public const SEARCH_NO_CLICK = 'search_no_click';

    public const ASSISTANT_OUTCOME = 'assistant_outcome';

    public const ASSISTANT_FEEDBACK = 'assistant_feedback';

    public function enabled(string $category): bool
    {
        return (bool) config('docent.insights.enabled', false)
            && (bool) config("docent.insights.categories.{$category}", true);
    }

    public function pageViewed(string $slug, string $surface): void
    {
        if (! $this->enabled('pages')) {
            return;
        }

        $this->create([
            'category' => 'pages',
            'event' => self::PAGE_VIEWED,
            'surface' => $this->surface($surface),
            'page_slug' => $this->slug($slug),
        ]);
    }

    /** @param list<SearchResult> $results */
    public function searchSubmitted(string $query, array $results, string $surface): ?string
    {
        if (! $this->enabled('search')) {
            return null;
        }

        $searchId = (string) Str::uuid();
        $slugs = array_values(array_unique(array_map(
            static fn (SearchResult $result): string => $result->slug,
            $results,
        )));
        $attributes = [
            'category' => 'search',
            'surface' => $this->surface($surface),
            'query' => $this->query($query),
            'search_id' => $searchId,
            'result_count' => count($results),
            'result_slugs' => $slugs,
        ];

        $this->create([...$attributes, 'event' => self::SEARCH_SUBMITTED]);
        $this->create([...$attributes, 'event' => self::SEARCH_RESULTS_IMPRESSED]);

        return $searchId;
    }

    public function searchInteraction(string $event, string $searchId, ?string $targetSlug = null): bool
    {
        if (! $this->enabled('search') || ! in_array($event, [self::SEARCH_RESULT_CLICKED, self::SEARCH_NO_CLICK], true)) {
            return false;
        }

        return DB::transaction(function () use ($event, $searchId, $targetSlug): bool {
            $search = InsightEvent::query()
                ->where('event', self::SEARCH_SUBMITTED)
                ->where('search_id', $searchId)
                ->lockForUpdate()
                ->first();

            if ($search === null) {
                return false;
            }

            $target = $targetSlug === null ? null : $this->slug($targetSlug);
            if ($event === self::SEARCH_RESULT_CLICKED
                && ($target === null || ! in_array($target, $search->result_slugs ?? [], true))) {
                return false;
            }

            if (InsightEvent::query()
                ->whereIn('event', [self::SEARCH_RESULT_CLICKED, self::SEARCH_NO_CLICK])
                ->where('search_id', $searchId)
                ->exists()) {
                return true;
            }

            $this->create([
                'category' => 'search',
                'event' => $event,
                'surface' => $search->surface,
                'query' => $search->query,
                'search_id' => $searchId,
                'target_slug' => $target,
            ]);

            return true;
        });
    }

    public function assistantOutcome(
        string $question,
        string $answer,
        AiCorpus $corpus,
        string $surface,
        string $pageSlug = '',
        ?AiQuestion $questionLog = null,
    ): void {
        if (! $this->enabled('assistant')) {
            return;
        }

        $this->create([
            'category' => 'assistant',
            'event' => self::ASSISTANT_OUTCOME,
            'surface' => $this->surface($surface),
            'page_slug' => $this->slug($pageSlug),
            'query' => $this->query($question),
            'reference_id' => $questionLog === null ? null : (string) $questionLog->getKey(),
            'status' => trim($answer) === '' ? 'unanswered' : 'answered',
            'citations' => array_values(array_filter(array_map(
                fn (array $citation): ?string => $this->slug((string) ($citation['slug'] ?? '')),
                $corpus->citations,
            ))),
        ]);
    }

    public function assistantFeedback(AiQuestion $question, string $feedback): void
    {
        if (! $this->enabled('assistant')) {
            return;
        }

        $outcome = InsightEvent::query()
            ->where('event', self::ASSISTANT_OUTCOME)
            ->where('reference_id', (string) $question->getKey())
            ->latest('id')
            ->first();

        if ($outcome === null) {
            return;
        }

        $existing = InsightEvent::query()
            ->where('event', self::ASSISTANT_FEEDBACK)
            ->where('reference_id', (string) $question->getKey())
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['feedback' => $feedback])->save();

            return;
        }

        $this->create([
            'category' => 'assistant',
            'event' => self::ASSISTANT_FEEDBACK,
            'surface' => $outcome->surface,
            'page_slug' => $outcome->page_slug,
            'query' => $outcome->query,
            'reference_id' => (string) $question->getKey(),
            'status' => $outcome->status,
            'citations' => $outcome->citations,
            'feedback' => $feedback,
        ]);
    }

    public function prune(?int $days = null): int
    {
        $retention = max(1, $days ?? (int) config('docent.insights.retention_days', 90));

        return InsightEvent::query()->where('created_at', '<', now()->subDays($retention))->delete();
    }

    /** @param array<string, mixed> $attributes */
    private function create(array $attributes): InsightEvent
    {
        return InsightEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            ...$attributes,
        ]);
    }

    private function query(string $value): ?string
    {
        if (! config('docent.insights.store_query_text', true)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if (config('docent.insights.redact_query_text', true)) {
            $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[email]', $value) ?? $value;
            $value = preg_replace('/https?:\/\/\S+/iu', '[url]', $value) ?? $value;
            $value = preg_replace('/\b(?:sk-[A-Za-z0-9_-]{12,}|[A-Za-z0-9_-]{32,})\b/u', '[secret]', $value) ?? $value;
            $value = preg_replace('/\b(?:\d[ -]*?){13,19}\b/u', '[number]', $value) ?? $value;
        }

        return mb_substr($value, 0, 500);
    }

    private function slug(string $value): ?string
    {
        $slug = trim($value, " \t\n\r\0\x0B/");

        return $slug === '' ? null : mb_substr($slug, 0, 255);
    }

    private function surface(string $surface): string
    {
        return $surface === 'widget' ? 'widget' : 'reader';
    }
}
