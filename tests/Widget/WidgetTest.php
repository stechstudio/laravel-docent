<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use STS\Docent\DocentManager;
use STS\Docent\DocentServiceProvider;

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

it('renders Assistant as a widget view only when AI is enabled', function () {
    config()->set('docent.ai.enabled', true);
    $this->app['router']->setRoutes(new RouteCollection);
    (new DocentServiceProvider($this->app))->boot();
    $this->app['router']->getRoutes()->refreshNameLookups();

    $this->get('/docs/_widget')
        ->assertOk()
        ->assertSee('data-docent-assistant-enabled', false)
        ->assertSee('data-docent-assistant-panel', false)
        ->assertSee('Ask Assistant')
        ->assertSee('aria-label="Open Assistant"', false)
        ->assertSee('id="docent-assistant-title-widget"', false)
        ->assertSee('aria-label="Open full docs"', false)
        ->assertDontSee('Temporary conversation. Answers are grounded in the docs available to you.');

    config()->set('docent.ai.enabled', false);

    $this->get('/docs/_widget')
        ->assertOk()
        ->assertDontSee('data-docent-assistant-enabled', false)
        ->assertDontSee('docentAssistant', false)
        ->assertDontSee('Ask Assistant');
});

it('renders visible sections as flat top-level widget groups', function () {
    $response = $this->actingAs($this->adminUser())
        ->get('/docs/_widget')
        ->assertOk()
        ->assertSeeInOrder(['Documentation', 'Reports'])
        ->assertDontSee('aria-label="Documentation sections"', false);

    expect(substr_count($response->getContent(), '>Reports</p>'))->toBe(1);
});

it('keeps pinned page links inside the widget and external links in a new tab', function () {
    config()->set('docent.navigation.links', [
        ['label' => 'Support', 'icon' => 'lifebuoy', 'url' => 'https://support.example.com'],
        ['label' => 'Setup guide', 'icon' => 'rocket-launch', 'page' => 'guides/setup'],
    ]);
    // Topbar utility links are docs-site chrome and stay out of the panel.
    config()->set('docent.navigation.topbar', [
        ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
    ]);

    $response = $this->get('/docs/_widget')
        ->assertOk()
        ->assertSeeInOrder(['Suggested for this page', 'Helpful links', 'Documentation'])
        ->assertSee('href="http://localhost/docs/_widget/guides/setup"', false)
        ->assertSee('href="https://support.example.com"', false)
        ->assertSee('target="_blank" rel="noopener"', false)
        ->assertDontSee('href="https://github.com/acme/acme"', false);

    expect($response->getContent())
        ->not->toMatch('/href="http:\/\/localhost\/docs\/_widget\/guides\/setup"[^>]*target="_top"/');
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
        ->toContain('"preload":false')
        ->toContain('\/_widget\/_suggestions')
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
        ->and(Route::getRoutes()->getByName('docent.widget.suggestions')?->uri())->toBe('docs/_widget/_suggestions')
        ->and(Route::getRoutes()->getByName('docent.widget.show')?->uri())->toBe('docs/_widget/{slug}');
});

it('returns deduplicated contextual suggestions that the viewer may open', function () {
    app(DocentManager::class)->suggest('billing.invoice', ['missing-page']);

    $this->getJson('/docs/_widget/_suggestions?page=billing.invoice')
        ->assertOk()
        ->assertJsonPath('page', 'billing.invoice')
        ->assertJsonCount(2, 'suggestions')
        ->assertJsonPath('suggestions.0.slug', 'welcome')
        ->assertJsonPath('suggestions.1.slug', 'guides/setup')
        ->assertJsonMissing(['slug' => 'billing/secret'])
        ->assertJsonMissing(['slug' => 'missing-page']);

    $this->actingAs($this->adminUser())
        ->getJson('/docs/_widget/_suggestions?page=billing.invoice')
        ->assertOk()
        ->assertJsonCount(3, 'suggestions')
        ->assertJsonFragment(['slug' => 'billing/secret']);
});

it('returns no contextual suggestions without a matching host page', function () {
    $this->getJson('/docs/_widget/_suggestions?page=reports.index')
        ->assertOk()
        ->assertJsonPath('page', 'reports.index')
        ->assertJsonCount(0, 'suggestions');
});

it('does not retain widget mode across application request scopes', function () {
    $docent = app(DocentManager::class);
    $docent->enableWidgetMode();

    expect($docent->url('guides/setup'))->toContain('/docs/_widget/guides/setup');

    $this->app->forgetScopedInstances();

    expect(app(DocentManager::class)->url('guides/setup'))->toBe('http://localhost/docs/guides/setup');
});

it('filters explicit slug overrides through the same authorization gate', function () {
    $guest = $this->getJson('/docs/_widget/_suggestions?slugs[]=welcome&slugs[]=billing/secret&slugs[]=missing-page')
        ->assertOk()
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonPath('suggestions.0.slug', 'welcome');

    $this->actingAs($this->adminUser())
        ->getJson('/docs/_widget/_suggestions?slugs[]=welcome&slugs[]=billing/secret')
        ->assertOk()
        ->assertJsonCount(2, 'suggestions');
});

it('seeds the launcher config with the current route name', function () {
    Route::get('/host-page', fn () => Blade::render('<x-docent::widget />'))
        ->name('host.dashboard')
        ->middleware('web');

    $this->get('/host-page')
        ->assertOk()
        ->assertSee('"page":"host.dashboard"', false);
});
