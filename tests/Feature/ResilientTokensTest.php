<?php

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use STS\Docent\Content\AgentFeed;
use STS\Docent\DocentManager;
use STS\Docent\Facades\Docent;
use STS\Docent\Runtime\IntegrationRegistry;

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

it('does not degrade a route token missing a bound parameter', function () {
    // Deterministic: it fails identically for every reader, so it is an
    // authoring defect to surface rather than session state to render around.
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/route-token-docs');
    Route::get('/needs/{id}', fn (): string => 'ok')->name('needs.param');

    renderHome();
})->throws(UrlGenerationException::class);

it('does not swallow a resolver class that cannot be constructed', function () {
    // A class-string naming nothing is a deployment defect every reader hits.
    // Instantiation happens outside the guard, so it must still blow up.
    app(DocentManager::class)->value('tenant.role', 'App\\Nope\\MissingResolver');

    renderHome();
})->throws(BindingResolutionException::class);

it('does not swallow a resolver that violates its return contract', function () {
    // The string conversion happens outside the guard for the same reason.
    app(DocentManager::class)->value('tenant.role', fn (): array => ['not', 'a', 'string']);

    renderHome();
})->throws(ErrorException::class);

it('propagates by default on a registry with no failure policy', function () {
    $registry = new IntegrationRegistry;
    $registry->value('boom', function (): string {
        throw new RuntimeException('Unhandled.');
    });

    $registry->resolveValue('boom', app(DocentManager::class)->guestContext());
})->throws(RuntimeException::class, 'Unhandled.');

it('reports a failing global registration exactly once', function () {
    Exceptions::fake();

    // Registered globally, resolved through the site registry that carries the
    // policy — the parent must stay pure so this is not reported twice.
    Docent::value('tenant.role', function (): string {
        throw new RuntimeException('Global resolver failed.');
    });
    app(DocentManager::class)->link('tenant.billing', fn (): string => '/billing');

    renderHome();

    Exceptions::assertReportedCount(1);
});

it('never caches an agent render that lost a token', function () {
    $failing = true;
    app(DocentManager::class)
        ->value('tenant.role', fn (): string => 'Owner')
        ->link('tenant.billing', function () use (&$failing): string {
            if ($failing) {
                throw new RuntimeException('No account selected.');
            }

            return '/billing';
        });

    $feed = app(AgentFeed::class);
    $docent = app(DocentManager::class);
    $render = fn (): string => $feed->agentMarkdown($docent->page(''), $docent->guestContext());

    expect($render())->not->toContain('/billing');

    // Same page, same fingerprint, resolver now healthy. A cached degraded
    // render would keep the link missing for every later reader.
    $failing = false;

    expect($render())->toContain('/billing');
});

it('still caches a healthy agent render', function () {
    $calls = 0;
    app(DocentManager::class)
        ->value('tenant.role', fn (): string => 'Owner')
        ->link('tenant.billing', function () use (&$calls): string {
            $calls++;

            return '/billing';
        });

    $feed = app(AgentFeed::class);
    $docent = app(DocentManager::class);
    $render = fn (): string => $feed->agentMarkdown($docent->page(''), $docent->guestContext());

    $render();
    $render();

    // The fixture holds two link occurrences, so one render is two calls. A
    // second render adding none proves the healthy result was cached.
    expect($calls)->toBe(2);
});
