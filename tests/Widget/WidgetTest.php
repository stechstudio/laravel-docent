<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use STS\Docent\DocentManager;

it('renders compact widget home chrome with widget navigation', function () {
    $response = $this->get('/docs/_widget')->assertOk();

    $response
        ->assertSee('How can we help?')
        ->assertSee('data-docent-widget', false)
        ->assertSee('data-docent-widget-search', false)
        ->assertSee('data-docent-widget-close', false)
        ->assertSee('href="http://localhost/docs/_widget/guides/setup"', false)
        ->assertDontSee('docent-sidebar', false)
        ->assertDontSee('On this page');
});

it('renders articles in widget chrome and keeps internal links sticky', function () {
    $this->get('/docs/_widget/welcome')
        ->assertOk()
        ->assertSee('Welcome Center')
        ->assertSee('href="http://localhost/docs/_widget/guides"', false)
        ->assertSee('href="http://localhost/docs/welcome" target="_top"', false)
        ->assertSee('All help')
        ->assertDontSee('AdminReportsCard');

    $this->actingAs($this->adminUser())
        ->get('/docs/_widget/welcome')
        ->assertOk()
        ->assertSee('AdminReportsCard');
});

it('keeps page authorization identical in widget mode', function () {
    $this->get('/docs/_widget/billing/secret')->assertNotFound();

    $this->actingAs($this->adminUser())
        ->get('/docs/_widget/billing/secret')
        ->assertOk()
        ->assertSee('Only billing admins can read this.');
});

it('rewrites existing search result urls for the widget', function () {
    $response = $this->getJson('/docs/_search?mode=widget&q=billing')->assertOk();
    $urls = collect($response->json('results'))->pluck('url');

    expect($urls)->not->toBeEmpty();
    foreach ($urls as $url) {
        expect($url)->toContain('/docs/_widget/');
    }
});

it('renders the explicit launcher component and validates its config', function () {
    config()->set('docent.widget.mode', 'push');
    config()->set('docent.widget.position', 'left');
    config()->set('docent.widget.offset', 18);
    config()->set('docent.widget.icon', 'not-a-real-icon');

    $html = Blade::render('<x-docent::widget />');

    expect($html)
        ->toContain('data-docent-widget-config')
        ->toContain('data-docent-widget-runtime')
        ->toContain('docent-widget.js')
        ->toContain('"mode":"push"')
        ->toContain('"position":"left"')
        ->toContain('"offset":18')
        ->toContain('\\u003Csvg')
        ->not->toContain('not-a-real-icon')
        ->toContain('window.Docent=window.Docent||function');
});

it('supports a custom icon url and launcher-free mode', function () {
    config()->set('docent.widget.icon', '/images/help.svg');
    config()->set('docent.widget.launcher', 'none');

    $html = Blade::render('<x-docent::widget />');

    expect($html)
        ->toContain('\\/images\\/help.svg')
        ->toContain('"launcher":"none"');
});

it('rejects unsafe custom icon urls', function () {
    config()->set('docent.widget.icon', 'javascript:alert(1)');

    $html = Blade::render('<x-docent::widget />');

    expect($html)
        ->toContain('\\u003Csvg')
        ->not->toContain('javascript:alert(1)');
});

it('serves the standalone widget runtime and registers routes before catch-all', function () {
    $this->get('/docs/_assets/docent-widget.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=utf-8');

    expect(Route::getRoutes()->getByName('docent.widget.home')?->uri())->toBe('docs/_widget')
        ->and(Route::getRoutes()->getByName('docent.widget.show')?->uri())->toBe('docs/_widget/{slug}');
});

it('does not retain widget mode across application request scopes', function () {
    $docent = app(DocentManager::class);
    $docent->enableWidgetMode();

    expect($docent->url('guides/setup'))->toContain('/docs/_widget/guides/setup');

    $this->app->forgetScopedInstances();

    expect(app(DocentManager::class)->url('guides/setup'))->toBe('http://localhost/docs/guides/setup');
});
