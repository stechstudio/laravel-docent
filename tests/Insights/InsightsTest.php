<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use STS\Docent\Ai\AiCorpus;
use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Insights\InsightSummary;
use STS\Docent\Insights\Models\InsightEvent;
use STS\Docent\Search\SearchEngine;

function fakeInsightAnswer(string $text): void
{
    Prism::fake([
        TextResponseFake::make()->withText($text)->withFinishReason(FinishReason::Stop),
    ])->withFakeChunkSize(12);
}

it('honors global and category collection switches', function () {
    config()->set('docent.insights.enabled', false);

    $this->get('/docs/guides/setup')->assertOk();
    $this->getJson('/docs/_search?q=setup')->assertOk()->assertJsonPath('insight_id', null);

    expect(InsightEvent::query()->count())->toBe(0);

    config()->set('docent.insights.enabled', true);
    config()->set('docent.insights.categories.pages', false);
    config()->set('docent.insights.categories.search', true);
    $this->resetDocentScope();

    $this->get('/docs/guides/setup')->assertOk();
    $this->getJson('/docs/_search?q=setup')->assertOk();

    expect(InsightEvent::query()->where('category', 'pages')->count())->toBe(0)
        ->and(InsightEvent::query()->where('category', 'search')->count())->toBe(1);
});

it('records reader and widget page views without viewer context', function () {
    $this->actingAs($this->adminUser())->get('/docs/guides/setup')->assertOk();
    $this->get('/docs/_widget/guides/setup')->assertOk();

    $events = InsightEvent::query()->where('event', InsightRecorder::PAGE_VIEWED)->get();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('surface')->all())->toBe(['reader', 'widget'])
        ->and($events->pluck('page_slug')->all())->toBe(['guides/setup', 'guides/setup'])
        ->and(array_keys($events->first()->getAttributes()))
        ->not->toContain('user_id', 'ip', 'session_id', 'referrer', 'user_agent', 'audience');
});

it('redacts query text and records impressions, clicks, and no-click searches', function () {
    $sensitive = 'setup joe@example.com sk-abcdefghijklmnopqrstuvwxyz123456 4111 1111 1111 1111';
    $first = $this->getJson('/docs/_search?q='.urlencode($sensitive))->assertOk();
    $searchId = $first->json('insight_id');
    $target = $first->json('results.0.slug');

    $this->postJson('/docs/_insights', [
        'event' => 'search_result_clicked',
        'search_id' => $searchId,
        'target_slug' => $target,
    ])->assertNoContent();

    $second = $this->getJson('/docs/_search?q=billing')->assertOk();
    $this->postJson('/docs/_insights', [
        'event' => 'search_no_click',
        'search_id' => $second->json('insight_id'),
    ])->assertNoContent();

    $stored = InsightEvent::query()->where('event', InsightRecorder::SEARCH_SUBMITTED)->oldest('id')->firstOrFail();
    $serialized = json_encode($stored->getAttributes(), JSON_THROW_ON_ERROR);

    expect($stored->query)->toContain('[email]', '[secret]', '[number]')
        ->and($serialized)->not->toContain('joe@example.com', 'abcdefghijklmnopqrstuvwxyz123456', '4111 1111')
        ->and($stored->result_count)->toBe($first->json('results') === [] ? 0 : count($first->json('results')))
        ->and($stored->result_slugs)->toContain($target)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)->count())->toBe(1)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_NO_CLICK)->count())->toBe(1);
});

it('folds typeahead refinements into one search row per session', function () {
    $first = $this->getJson('/docs/_search?q=ins')->assertOk();
    $insightId = $first->json('insight_id');

    $second = $this->getJson('/docs/_search?q=install+setup&insight_id='.$insightId)->assertOk();

    // The refinement updated the open session row instead of adding a prefix row.
    expect($second->json('insight_id'))->toBe($insightId)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_SUBMITTED)->count())->toBe(1)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_SUBMITTED)->sole()->query)->toBe('install setup');

    // A terminal interaction closes the session: the same id can no longer be continued.
    $this->postJson('/docs/_insights', [
        'event' => 'search_no_click',
        'search_id' => $insightId,
    ])->assertNoContent();

    $third = $this->getJson('/docs/_search?q=install+setup+again&insight_id='.$insightId)->assertOk();

    expect($third->json('insight_id'))->not->toBe($insightId)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_SUBMITTED)->count())->toBe(2);
});

it('rejects forged search clicks and deduplicates terminal search events', function () {
    $response = $this->getJson('/docs/_search?q=setup')->assertOk();
    $searchId = $response->json('insight_id');
    $target = $response->json('results.0.slug');

    $this->postJson('/docs/_insights', [
        'event' => 'search_result_clicked',
        'search_id' => $searchId,
        'target_slug' => 'billing/secret',
    ])->assertNotFound();

    foreach ([1, 2] as $attempt) {
        $this->postJson('/docs/_insights', [
            'event' => 'search_result_clicked',
            'search_id' => $searchId,
            'target_slug' => $target,
        ])->assertNoContent();
    }

    $this->postJson('/docs/_insights', [
        'event' => 'search_no_click',
        'search_id' => $searchId,
    ])->assertNoContent();

    expect(InsightEvent::query()->where('event', InsightRecorder::SEARCH_RESULT_CLICKED)->count())->toBe(1)
        ->and(InsightEvent::query()->where('event', InsightRecorder::SEARCH_NO_CLICK)->count())->toBe(0);
});

it('records assistant outcomes, citations, and feedback without answer transcripts', function () {
    fakeInsightAnswer('A private answer that must never be stored in insights.');

    $response = $this->postJson('/docs/_ask', [
        'question' => 'How do I install it?',
        'current_slug' => 'guides/setup',
    ])->assertOk();
    $stream = $response->streamedContent();
    preg_match('/"question_id":(\d+),"feedback_token":"([0-9a-f]{64})"/', $stream, $matches);

    $this->postJson('/docs/_ask/feedback', [
        'question_id' => (int) ($matches[1] ?? 0),
        'feedback_token' => $matches[2] ?? '',
        'thumbs' => 'down',
    ])->assertNoContent();

    $outcome = InsightEvent::query()->where('event', InsightRecorder::ASSISTANT_OUTCOME)->sole();
    $feedback = InsightEvent::query()->where('event', InsightRecorder::ASSISTANT_FEEDBACK)->sole();
    $serialized = InsightEvent::query()->get()->toJson();

    expect($outcome->status)->toBe('answered')
        ->and($outcome->page_slug)->toBe('guides/setup')
        ->and($outcome->citations)->toContain('guides/setup')
        ->and($feedback->feedback)->toBe('down')
        ->and($serialized)->not->toContain('A private answer that must never be stored');
});

it('aggregates useful summaries and protects the admin view and export', function () {
    $recorder = app(InsightRecorder::class);
    $recorder->pageViewed('guides/setup', 'reader');
    $recorder->pageViewed('guides/setup', 'widget');

    $results = app(SearchEngine::class)->search('setup', $this->contextFor(null));
    $clicked = $recorder->searchSubmitted('install setup', $results, 'reader');
    $recorder->searchInteraction(InsightRecorder::SEARCH_RESULT_CLICKED, $clicked, $results[0]->slug);
    $recorder->searchSubmitted('install setup', $results, 'widget');
    $recorder->searchSubmitted('missing answer', [], 'reader');

    $corpus = new AiCorpus('docs', [], 'stable', 'retrieval', 1, false, 0);
    $question = AiQuestion::query()->create([
        'question' => 'What is missing?', 'status' => 'no-answer', 'viewer_class' => 'guest',
    ]);
    $recorder->assistantOutcome('What is missing?', '', $corpus, 'reader', '', $question);
    $recorder->assistantFeedback($question, 'down');

    $summary = app(InsightSummary::class)->forDays(30);
    $lowCtr = collect($summary['low_ctr_searches'])->firstWhere('query', 'install setup');

    expect($summary['top_pages'][0])->toBe(['label' => 'guides/setup', 'count' => 2])
        ->and($summary['top_searches'][0])->toBe(['label' => 'install setup', 'count' => 2])
        ->and($lowCtr['ctr'] ?? null)->toBe(50.0)
        ->and($summary['unanswered_questions'][0]['label'] ?? null)->toBe('What is missing?')
        ->and($summary['negative_feedback'][0]['label'] ?? null)->toBe('What is missing?');

    $this->actingAs($this->memberUser())->get('/docs/admin/insights')->assertForbidden();
    $this->actingAs($this->adminUser())->get('/docs/admin/insights')
        ->assertOk()
        ->assertSee('Documentation insights')
        ->assertSee('Low-click searches')
        ->assertSee('What is missing?');

    $csv = $this->get('/docs/admin/insights.csv')->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('created_at,category,event,surface')
        ->toContain('guides/setup')
        ->not->toContain('answer transcripts');
});

it('prunes events using the configured retention window', function () {
    config()->set('docent.insights.retention_days', 30);

    InsightEvent::query()->create([
        'event_id' => (string) Str::uuid(),
        'category' => 'pages',
        'event' => InsightRecorder::PAGE_VIEWED,
        'surface' => 'reader',
        'page_slug' => 'old',
        'created_at' => now()->subDays(31),
    ]);
    app(InsightRecorder::class)->pageViewed('fresh', 'reader');

    $this->artisan('docent:insights:prune')->assertSuccessful();

    expect(InsightEvent::query()->pluck('page_slug')->all())->toBe(['fresh']);
});
