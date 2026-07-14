<?php

declare(strict_types=1);

use STS\Docent\Search\SearchEngine;

function relevanceSearch($test, string $query, $user = null): array
{
    return app(SearchEngine::class)->search($query, $test->contextFor($user));
}

it('meets the curated natural-language relevance contract', function () {
    $cases = [
        ['query' => 'how do I insert a video', 'expected' => 'media/video'],
        ['query' => 'insert a video', 'expected' => 'media/video'],
        ['query' => 'vide', 'expected' => 'media/video'],
        ['query' => 'vedeo', 'expected' => 'media/video'],
        ['query' => 'passwords', 'expected' => 'account/password'],
        ['query' => 'forgot password', 'expected' => 'account/password'],
        ['query' => 'payment card', 'expected' => 'billing/payment-methods'],
        ['query' => 'update billing card', 'expected' => 'billing/payment-methods'],
    ];

    $topOne = 0;
    $topThree = 0;

    foreach ($cases as $case) {
        $slugs = array_map(static fn ($result): string => $result->slug, relevanceSearch($this, $case['query']));
        $topOne += ($slugs[0] ?? null) === $case['expected'] ? 1 : 0;
        $topThree += in_array($case['expected'], array_slice($slugs, 0, 3), true) ? 1 : 0;
    }

    expect($topOne / count($cases))->toBeGreaterThanOrEqual(0.75)
        ->and($topThree / count($cases))->toBeGreaterThanOrEqual(0.90);
});

it('uses the strongest matching section for its anchor and snippet', function () {
    $result = relevanceSearch($this, 'forgot password')[0] ?? null;

    expect($result?->slug)->toBe('account/password')
        ->and($result?->heading)->toBe('Forgot your password')
        ->and($result?->anchor)->toBe('forgot-your-password')
        ->and($result?->snippet)->toContain('recovery email');
});

it('keeps hidden excluded and unauthorized content out of ranked results', function () {
    $hidden = array_map(fn ($result) => $result->slug, relevanceSearch($this, 'hidden-only-search-phrase'));
    $excluded = array_map(fn ($result) => $result->slug, relevanceSearch($this, 'excluded-only-search-phrase'));
    $guest = array_map(fn ($result) => $result->slug, relevanceSearch($this, 'confidential compliance'));

    expect($hidden)->not->toContain('internal-hidden')
        ->and($excluded)->not->toContain('excluded')
        ->and($guest)->not->toContain('reports/compliance');

    $admin = relevanceSearch($this, 'confidential compliance', $this->adminUser());

    expect($admin[0]?->slug)->toBe('reports/compliance');
});

it('returns no result when no meaningful term matches', function () {
    expect(relevanceSearch($this, 'how do I flibbertigibbet this'))->toBe([]);
});

it('uses keywords for ranking without leaking them into snippets', function () {
    $result = relevanceSearch($this, 'upload a movie')[0] ?? null;

    expect($result?->slug)->toBe('media/video')
        ->and($result?->snippet)->not->toContain('upload a movie');
});

it('does not treat a non-final query term as a typeahead prefix', function () {
    $result = relevanceSearch($this, 'paym card')[0] ?? null;

    expect($result?->slug)->toBe('billing/payment-methods')
        ->and($result?->snippet)->not->toContain('<mark>Payment</mark>')
        ->and($result?->snippet)->toContain('<mark>card</mark>');
});

it('bounds typo tolerance to one edit on terms of at least five characters', function () {
    expect(relevanceSearch($this, 'vedeo')[0]?->slug)->toBe('media/video')
        ->and(relevanceSearch($this, 'vedeoo'))->toBe([]);
});

it('breaks equal scores by stable index order', function () {
    $slugs = array_map(fn ($result) => $result->slug, relevanceSearch($this, 'shared deterministic ranking phrase'));

    expect(array_values(array_intersect($slugs, ['ties/alpha', 'ties/beta'])))
        ->toBe(['ties/alpha', 'ties/beta']);
});
