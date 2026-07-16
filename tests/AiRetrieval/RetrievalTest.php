<?php

declare(strict_types=1);

use STS\Docent\Ai\AiConversationTurn;
use STS\Docent\Ai\AiCorpusBuilder;
use STS\Docent\Ai\AiPrompt;

function retrievedCorpus($test, string $question, array $history = [], string $currentSlug = '', $user = null)
{
    return app(AiCorpusBuilder::class)->build(
        $test->contextFor($user),
        $question,
        $history,
        $currentSlug,
    );
}

it('retrieves a relevant page late in the corpus instead of truncating by navigation order', function () {
    config()->set('docent.ai.retrieval.max_pages', 1);
    config()->set('docent.ai.corpus_budget', 180);

    $corpus = retrievedCorpus($this, 'how do I insert a video');

    expect($corpus->citations)->toHaveCount(1)
        ->and($corpus->citations[0]['slug'])->toBe('media/video')
        ->and($corpus->content)->toContain('Images and videos')
        ->toContain('frame component')
        ->not->toContain('Payment methods');
});

it('returns multiple deterministic sources for an ambiguous query', function () {
    $corpus = retrievedCorpus($this, 'shared deterministic ranking phrase');
    $slugs = array_column($corpus->citations, 'slug');

    expect(array_values(array_intersect($slugs, ['ties/alpha', 'ties/beta'])))
        ->toBe(['ties/alpha', 'ties/beta']);
});

it('uses recent conversation context only for likely follow-ups', function () {
    $history = [new AiConversationTurn(
        'How do I insert a video?',
        'Use the media guide.',
        time(),
    )];

    $followUp = retrievedCorpus($this, 'What about that?', $history);
    $newTopic = retrievedCorpus($this, 'How do I update a payment card?', $history);

    expect(array_column($followUp->citations, 'slug'))->toContain('media/video')
        ->and($followUp->diagnostics['follow_up_context_used'])->toBeTrue()
        ->and($newTopic->citations[0]['slug'] ?? null)->toBe('billing/payment-methods')
        ->and($newTopic->diagnostics['follow_up_context_used'])->toBeFalse();
});

it('deliberately includes the current searchable page for a vague question', function () {
    $corpus = retrievedCorpus($this, 'What should I do next?', currentSlug: 'billing/payment-methods');
    $selected = collect($corpus->diagnostics['selected'])->firstWhere('slug', 'billing/payment-methods');

    expect(array_column($corpus->citations, 'slug'))->toContain('billing/payment-methods')
        ->and($corpus->diagnostics['current_page_included'])->toBeTrue()
        ->and($selected['reasons'] ?? [])->toContain('current_page');
});

it('never retrieves excluded or unauthorized pages and allows them after authorization', function () {
    $excluded = retrievedCorpus($this, 'excluded-only-search-phrase');
    $guest = retrievedCorpus($this, 'confidential compliance');
    $admin = retrievedCorpus($this, 'confidential compliance', user: $this->adminUser());

    expect(array_column($excluded->citations, 'slug'))->not->toContain('excluded')
        ->and($excluded->content)->not->toContain('excluded-only-search-phrase')
        ->and(array_column($guest->citations, 'slug'))->not->toContain('reports/compliance')
        ->and($guest->content)->not->toContain('confidential compliance archive')
        ->and(array_column($admin->citations, 'slug'))->toContain('reports/compliance')
        ->and($admin->content)->toContain('confidential compliance archive');
});

it('returns a no-answer corpus when no indexed term matches', function () {
    $corpus = retrievedCorpus($this, 'flibbertigibbet zephyroscope');

    expect($corpus->citations)->toBe([])
        ->and($corpus->content)->toContain('no relevant documentation was retrieved')
        ->and(AiPrompt::system($corpus))->toContain('If the documentation does not contain the answer');
});

it('keeps diagnostics useful without including documentation content', function () {
    $corpus = retrievedCorpus($this, 'insert video');
    $diagnostics = json_encode($corpus->diagnostics, JSON_THROW_ON_ERROR);

    expect($corpus->diagnostics)
        ->toHaveKeys(['candidate_count', 'selected_count', 'included_count', 'estimated_tokens', 'selected'])
        ->and($corpus->diagnostics['selected'][0]['slug'] ?? null)->toBe('media/video')
        ->and($diagnostics)
        ->not->toContain('frame component')
        ->not->toContain('optional caption');
});

it('keeps conversation identity stable while retrieval changes by question', function () {
    $video = retrievedCorpus($this, 'insert video');
    $billing = retrievedCorpus($this, 'update billing card');

    expect($video->version)->toBe($billing->version)
        ->and($video->retrievalVersion)->not->toBe($billing->retrievalVersion);
});
