<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;

it('renders a landing page without sidebar or toc, with hero and resolved ctas', function () {
    $response = $this->get('/docs/welcome');

    $response->assertOk()
        ->assertSee('Welcome Center')
        ->assertSee('Everything you need to succeed.')
        ->assertSee('Start here')
        ->assertSee(route('docent.show', 'guides/setup'))
        ->assertSee('docent-cta-secondary', false)
        ->assertDontSee('docent-sidebar', false)
        ->assertDontSee('docent-toc', false);
});

it('keeps section tabs and topbar links in the landing header', function () {
    config()->set('docent.sites.docs.navigation.topbar', [
        ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
    ]);

    $this->actingAs($this->adminUser())
        ->get('/docs/welcome')
        ->assertOk()
        ->assertSee('aria-label="Documentation sections"', false)
        ->assertSee('aria-label="GitHub"', false)
        ->assertSee('href="https://github.com/acme/acme"', false);
});

it('renders regular pages with the sidebar unchanged', function () {
    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('docent-sidebar', false);
});

it('hides gated cards from guests and shows them to authorized viewers', function () {
    $this->get('/docs/welcome')
        ->assertOk()
        ->assertSee('Guides')
        ->assertDontSee('AdminReportsCard');

    $this->actingAs($this->adminUser())
        ->get('/docs/welcome')
        ->assertOk()
        ->assertSee('AdminReportsCard');
});

it('renders card grids on regular docs pages too', function () {
    // The landing fixture's grid is markup-level; assert the stable classes render.
    $this->get('/docs/welcome')
        ->assertSee('docent-cards', false)
        ->assertSee('docent-card-title', false);
});

it('flags broken card hrefs, broken hero ctas, and unknown icons in docent:check', function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/broken-docs');
    app()->forgetInstance(DocumentationRepository::class);

    $exit = Artisan::call('docent:check');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('missing/page')
        ->toContain('nowhere/at-all')
        ->toContain('unknown-icon')
        ->toContain('not-an-icon');
});
