<?php

use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;

function docentNav($testCase, $user = null): array
{
    return app(DocentManager::class)->navigation($testCase->contextFor($user));
}

function findGroup(array $nav, string $label): ?NavigationGroup
{
    foreach ($nav as $node) {
        if ($node instanceof NavigationGroup && $node->label === $label) {
            return $node;
        }
    }

    return null;
}

it('lists root pages and groups', function () {
    $nav = docentNav($this);

    $rootItems = array_filter($nav, fn ($n) => $n instanceof NavigationItem);
    $rootTitles = array_map(fn ($n) => $n->title, array_values($rootItems));

    expect($rootTitles)->toBe(['Home', 'Changelog']);
    expect(findGroup($nav, 'Guides'))->not->toBeNull();
    expect(findGroup($nav, 'Billing'))->not->toBeNull();
});

it('uses _group.yml labels and orders groups', function () {
    $nav = docentNav($this);

    $groups = array_values(array_filter($nav, fn ($n) => $n instanceof NavigationGroup));
    $labels = array_map(fn ($g) => $g->label, $groups);

    // Reports is hidden for a guest, so only Guides then Billing remain, in order.
    expect($labels)->toBe(['Guides', 'Billing']);
    expect($groups[0]->icon)->toBe('book');
});

it('orders pages within a group by front matter order then title', function () {
    $guides = findGroup(docentNav($this), 'Guides');

    expect(array_map(fn ($i) => $i->title, $guides->items))
        ->toBe(['Guides Overview', 'Setup', 'Cycle']);
});

it('excludes hidden pages from navigation', function () {
    $guides = findGroup(docentNav($this), 'Guides');

    expect(array_map(fn ($i) => $i->title, $guides->items))->not->toContain('Advanced');
});

it('filters unauthorized pages and empty groups per viewer', function () {
    $guestNav = docentNav($this);
    expect(findGroup($guestNav, 'Reports'))->toBeNull();

    $billing = findGroup($guestNav, 'Billing');
    expect(array_map(fn ($i) => $i->title, $billing->items))->toBe(['Billing Overview']);

    $adminNav = docentNav($this, $this->adminUser());
    expect(findGroup($adminNav, 'Reports'))->not->toBeNull();

    $adminBilling = findGroup($adminNav, 'Billing');
    expect(array_map(fn ($i) => $i->title, $adminBilling->items))->toBe(['Billing Overview', 'Secret Billing']);
});

it('builds item urls through the docs routes', function () {
    $guides = findGroup(docentNav($this), 'Guides');
    $setup = collect($guides->items)->firstWhere('slug', 'guides/setup');

    expect($setup->url)->toBe(url('/docs/guides/setup'))
        ->and($setup->active('guides/setup'))->toBeTrue()
        ->and($setup->active('guides'))->toBeFalse();
});

it('computes prev/next from the flattened filtered navigation', function () {
    [$prev, $next] = app(DocentManager::class)->prevNext('guides/setup', $this->contextFor(null));

    expect($prev->slug)->toBe('guides')
        ->and($next->slug)->toBe('guides/cycle');
});
