<?php

use STS\Docent\DocentManager;

it('renders the hero badge and search box on a landing page', function () {
    $this->get('/docs/hub')
        ->assertOk()
        ->assertSee('docent-hero-badge', false)
        ->assertSee('Help Center')
        ->assertSee('docent-search-box-lg', false)
        ->assertDontSee('docent-sidebar', false);
});

it('omits the hero search box when search is disabled', function () {
    config()->set('docent.search.enabled', false);

    $this->get('/docs/hub')
        ->assertOk()
        ->assertDontSee('docent-search-box-lg', false);
});

it('renders permission-aware section cards from the navigation tree', function () {
    $this->get('/docs/hub')
        ->assertOk()
        ->assertSee('Guides')
        ->assertSee('Billing')
        ->assertSee('1 article')
        ->assertDontSee('Reports');

    $this->actingAs($this->adminUser())
        ->get('/docs/hub')
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('2 articles');
});

it('scopes section cards to one directory and skips its index page', function () {
    $docent = app(DocentManager::class);
    $cards = $docent->sectionCards('guides', $this->contextFor(null));

    expect(array_column($cards, 'title'))->toContain('Setup')
        ->not->toContain('Guides Overview');
});

it('resolves a named layout through the config map', function () {
    config()->set('docent.layouts.hub', 'docent::layouts.landing');
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/docs');

    file_put_contents(dirname(__DIR__).'/fixtures/docs/custom-layout.md', <<<'MD'
    ---
    title: Custom Layout Page
    layout: hub
    hidden: true
    ---

    Body of the custom layout page.
    MD);

    try {
        $this->get('/docs/custom-layout')
            ->assertOk()
            ->assertSee('Custom Layout Page')
            ->assertDontSee('docent-sidebar', false);
    } finally {
        unlink(dirname(__DIR__).'/fixtures/docs/custom-layout.md');
    }
});

it('fails loudly for an unknown layout instead of falling back', function () {
    file_put_contents(dirname(__DIR__).'/fixtures/docs/broken-layout.md', <<<'MD'
    ---
    title: Broken Layout Page
    layout: does-not-exist
    hidden: true
    ---

    Body.
    MD);

    try {
        $this->get('/docs/broken-layout')->assertStatus(500);
    } finally {
        unlink(dirname(__DIR__).'/fixtures/docs/broken-layout.md');
    }
});

it('defers the topbar search to a hero search box', function () {
    $this->get('/docs/hub')
        ->assertOk()
        ->assertSee('data-docent-search-deferred', false);

    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('data-docent-topbar-search', false)
        ->assertDontSee('data-docent-search-deferred', false);
});

it('lets a custom layout override the topbar regions', function () {
    view()->addNamespace('doctest', dirname(__DIR__).'/fixtures/views');
    config()->set('docent.layouts.custom-chrome', 'doctest::custom-topbar');

    file_put_contents(dirname(__DIR__).'/fixtures/docs/custom-chrome.md', <<<'MD'
    ---
    title: Custom Chrome Page
    layout: custom-chrome
    hidden: true
    ---

    Body.
    MD);

    try {
        $this->get('/docs/custom-chrome')
            ->assertOk()
            ->assertSee('CustomNav')
            ->assertSee('aria-label="Toggle dark mode"', false)
            ->assertDontSee('data-docent-topbar-search', false);
    } finally {
        unlink(dirname(__DIR__).'/fixtures/docs/custom-chrome.md');
    }
});

it('exposes hero front matter accessors', function () {
    $page = app(DocentManager::class)->page('hub');

    expect($page->heroBadge())->toBe('Help Center')
        ->and($page->heroSearch())->toBeTrue()
        ->and($page->layout())->toBe('landing');
});
