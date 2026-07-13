<?php

use Illuminate\Support\Facades\Route;
use STS\Docent\DocentManager;

beforeEach(function () {
    Route::get('/host/admin', fn () => 'Admin')->name('host.admin');
    // Routes registered after boot are not in the name lookup table until it
    // refreshes; real requests refresh it during dispatch.
    Route::getRoutes()->refreshNameLookups();

    config()->set('docent.navigation.links', [
        ['label' => 'Support', 'icon' => 'lifebuoy', 'url' => 'https://support.example.com'],
        ['label' => 'Setup guide', 'icon' => 'rocket-launch', 'page' => 'guides/setup'],
        ['label' => 'Admin console', 'icon' => 'wrench', 'route' => 'host.admin', 'can' => 'reports.view'],
        ['label' => 'Secret guide', 'page' => 'billing/secret'],
    ]);
});

it('resolves url page and route links for the current viewer', function () {
    $manager = app(DocentManager::class);
    $guest = $manager->navigationLinks($this->contextFor(null), 'guides/setup');
    $admin = $manager->navigationLinks($this->contextFor($this->adminUser()), 'guides/setup');

    expect(array_map(fn ($link) => $link->label, $guest))
        ->toBe(['Support', 'Setup guide'])
        ->and($guest[0]->url)->toBe('https://support.example.com')
        ->and($guest[0]->external)->toBeTrue()
        ->and($guest[1]->url)->toBe(url('/docs/guides/setup'))
        ->and($guest[1]->active)->toBeTrue()
        ->and(array_map(fn ($link) => $link->label, $admin))
        ->toBe(['Support', 'Setup guide', 'Admin console', 'Secret guide'])
        ->and($admin[2]->url)->toBe(url('/host/admin'));
});

it('renders pinned links at the top of desktop and mobile navigation', function () {
    $response = $this->get('/docs/guides/setup')->assertOk();

    $response
        ->assertSee('aria-label="Helpful links"', false)
        ->assertSee('href="https://support.example.com"', false)
        ->assertSee('target="_blank" rel="noopener"', false)
        ->assertSee('href="http://localhost/docs/guides/setup"', false)
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('Admin console')
        ->assertDontSee('Secret guide');

    expect(substr_count($response->getContent(), 'aria-label="Helpful links"'))->toBe(2);
});

it('drops invalid entries and invalid icons without breaking navigation', function () {
    config()->set('docent.navigation.links', [
        ['label' => 'Too many', 'page' => 'guides/setup', 'url' => 'https://example.com'],
        ['label' => '', 'url' => 'https://example.com'],
        ['label' => 'Missing page', 'page' => 'missing'],
        ['label' => 'Missing route', 'route' => 'missing.route'],
        ['label' => 'No icon', 'icon' => 'not-real', 'url' => '/help'],
        ['label' => 'Image icon', 'icon' => '/images/support.svg', 'url' => '/support'],
    ]);

    $links = app(DocentManager::class)->navigationLinks($this->contextFor(null));

    expect(array_map(fn ($link) => $link->label, $links))->toBe(['No icon', 'Image icon'])
        ->and($links[0]->iconMarkup())->toBeNull()
        ->and($links[1]->iconMarkup())->toContain('/images/support.svg');
});

it('resolves topbar utility links with the same gating rules', function () {
    config()->set('docent.navigation.topbar', [
        ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
        ['label' => 'Internal tools', 'icon' => 'wrench', 'route' => 'host.admin', 'can' => 'reports.view'],
    ]);

    $manager = app(DocentManager::class);
    $guest = $manager->topbarLinks($this->contextFor(null));
    $admin = $manager->topbarLinks($this->contextFor($this->adminUser()));

    expect(array_map(fn ($link) => $link->label, $guest))->toBe(['GitHub'])
        ->and($guest[0]->external)->toBeTrue()
        ->and($guest[0]->iconMarkup())->toContain('<svg')
        ->and(array_map(fn ($link) => $link->label, $admin))->toBe(['GitHub', 'Internal tools']);
});

it('renders topbar links as labeled icon buttons in the header', function () {
    config()->set('docent.navigation.topbar', [
        ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
    ]);

    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('aria-label="GitHub"', false)
        ->assertSee('href="https://github.com/acme/acme"', false);
});

it('bundles brand icons for common utility destinations', function () {
    foreach (['github', 'discord', 'slack', 'x-twitter', 'youtube'] as $name) {
        expect(\STS\Docent\Support\Icon::has($name))->toBeTrue()
            ->and(\STS\Docent\Support\Icon::svg($name))->toContain('fill="currentColor"');
    }
});

it('does not include persistent links in agent discovery surfaces', function () {
    $this->get('/docs/llms.txt')
        ->assertOk()
        ->assertDontSee('Support')
        ->assertDontSee('Admin console');

    $this->actingAs($this->adminUser())
        ->get('/docs/llms-full.txt')
        ->assertOk()
        ->assertDontSee('support.example.com', false);
});
