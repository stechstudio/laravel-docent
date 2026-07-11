<?php

use STS\Docent\DocentManager;

function renderSetup($testCase, $user = null): string
{
    return app(DocentManager::class)->page('guides/setup')->render($testCase->contextFor($user));
}

it('resolves includes through repository partials', function () {
    expect(renderSetup($this))->toContain('You need the correct permissions');
});

it('resolves dynamic values, always escaped', function () {
    expect(renderSetup($this))->toContain('Your plan is Team Plan');
});

it('resolves app links to registered urls', function () {
    expect(renderSetup($this))->toContain('href="/billing/settings"');
});

it('renders components through the container', function () {
    $html = renderSetup($this);

    expect($html)->toContain('Usage for the pro plan')
        ->and($html)->toContain('data-plan="pro"');
});

it('shows authorization blocks only to permitted viewers', function () {
    expect(renderSetup($this, $this->adminUser()))->toContain('You can manage billing');
    expect(renderSetup($this))->not->toContain('You can manage billing');
});

it('renders condition blocks based on registered conditions', function () {
    expect(renderSetup($this))->not->toContain('Beta features are enabled');

    config()->set('docent_test.beta', true);

    expect(renderSetup($this))->toContain('Beta features are enabled');
});

it('is cycle-safe when partials include themselves', function () {
    $html = app(DocentManager::class)->page('guides/cycle')->render($this->contextFor(null));

    expect($html)->toContain('Loop start')
        ->and($html)->toContain('Loop end')
        ->and(substr_count($html, 'Loop start'))->toBe(1);
});

it('resolves slug-style internal links to docs urls', function () {
    $html = app(DocentManager::class)->page('')->render($this->contextFor(null));

    expect($html)->toContain('href="'.url('/docs/guides/setup').'"');
});
