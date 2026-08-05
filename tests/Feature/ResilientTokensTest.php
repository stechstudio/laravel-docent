<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use STS\Docent\Content\AgentFeed;
use STS\Docent\DocentManager;
use STS\Docent\Search\SearchEngine;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/resilient-docs');
});

/**
 * Register a value and a link that both blow up the way a tenant-scoped closure
 * does for a reader with no tenant selected.
 */
function registerThrowingTokens(): void
{
    app(DocentManager::class)
        ->value('tenant.role', function (): string {
            throw new RuntimeException('No account selected.');
        })
        ->link('tenant.billing', function (): string {
            throw new RuntimeException('Missing required parameter for route.');
        });
}

function renderHome(): string
{
    $docent = app(DocentManager::class);

    return $docent->page('')->render($docent->guestContext());
}

it('renders the rest of the page when a value closure throws', function () {
    registerThrowingTokens();

    expect(renderHome())
        ->toContain('Opening paragraph')
        ->toContain('Closing paragraph')
        ->toContain('Your role is');
});

it('renders the rest of the page when a link closure throws', function () {
    registerThrowingTokens();

    // The markdown link keeps its label, unlinked — the same treatment an
    // unresolvable app link already receives.
    expect(renderHome())->toContain('billing settings');
});

it('reports the exception so it still reaches exception tracking', function () {
    Exceptions::fake();
    registerThrowingTokens();

    renderHome();

    Exceptions::assertReported(RuntimeException::class);
});

it('substitutes nothing for the failed token', function () {
    registerThrowingTokens();

    expect(renderHome())->not->toContain('No account selected');
});

it('rethrows when strict_tokens is enabled', function () {
    config()->set('docent.render.strict_tokens', true);
    registerThrowingTokens();

    renderHome();
})->throws(RuntimeException::class, 'No account selected.');

it('leaves a healthy token entirely alone', function () {
    app(DocentManager::class)
        ->value('tenant.role', fn (): string => 'Owner')
        ->link('tenant.billing', fn (): string => '/billing');

    expect(renderHome())
        ->toContain('Your role is Owner')
        ->toContain('href="/billing"');
});

it('keeps the page rendering in the agent markdown feed too', function () {
    registerThrowingTokens();

    $markdown = app(AgentFeed::class)
        ->agentMarkdown(app(DocentManager::class)->page(''), app(DocentManager::class)->guestContext());

    expect($markdown)
        ->toContain('Opening paragraph')
        ->toContain('Closing paragraph');
});

it('survives a route token missing a bound parameter', function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/route-token-docs');
    Route::get('/needs/{id}', fn (): string => 'ok')->name('needs.param');

    expect(renderHome())
        ->toContain('Opening paragraph')
        ->toContain('Closing paragraph');
});

it('keeps search indexing alive when a value closure throws', function () {
    registerThrowingTokens();

    expect(app(SearchEngine::class)
        ->search('Closing paragraph', app(DocentManager::class)->guestContext()))
        ->not->toBeEmpty();
});
