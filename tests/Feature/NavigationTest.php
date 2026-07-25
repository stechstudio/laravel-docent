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

    // The directory's own index.md is promoted to the group header instead of
    // being listed among its pages.
    expect(array_map(fn ($i) => $i->title, $guides->items))
        ->toBe(['Setup', 'Cycle'])
        ->and($guides->index?->title)->toBe('Guides Overview');
});

it('excludes hidden pages from navigation', function () {
    $guides = findGroup(docentNav($this), 'Guides');

    expect(array_map(fn ($i) => $i->title, $guides->items))->not->toContain('Advanced');
});

it('filters unauthorized pages and empty groups per viewer', function () {
    $guestNav = docentNav($this);
    expect(findGroup($guestNav, 'Reports'))->toBeNull();

    $billing = findGroup($guestNav, 'Billing');
    expect(array_map(fn ($i) => $i->title, $billing->items))->toBe(['Billing Overview'])
        ->and($billing->index)->toBeNull();  // billing/ has no index.md

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

it('treats a promoted index page as part of its group', function () {
    $guides = findGroup(docentNav($this), 'Guides');

    // contains() drives breadcrumbs and sidebar auto-expansion, so landing on
    // the group's own page must still resolve to the group.
    expect($guides->contains('guides'))->toBeTrue()
        ->and($guides->contains('guides/setup'))->toBeTrue()
        ->and($guides->contains('billing/overview'))->toBeFalse();
});

it('drops a gated index page from the group it heads', function () {
    // reports/ holds nothing but an index.md gated behind reports.view, so the
    // whole group filters away for a guest and the admin sees an index-only
    // group — the header-link-without-a-chevron case.
    expect(findGroup(docentNav($this), 'Reports'))->toBeNull();

    $reports = findGroup(docentNav($this, $this->adminUser()), 'Reports');

    expect($reports->index?->slug)->toBe('reports')
        ->and($reports->items)->toBe([])
        ->and($reports->groups)->toBe([]);
});

it('keeps a nested index page out of its own item list', function () {
    $guides = findGroup(docentNav($this), 'Guides');
    $nested = collect($guides->groups)->keyBy('label');

    expect($nested->get('Deploy')->index?->slug)->toBe('guides/deploy')
        ->and(array_map(fn ($i) => $i->title, $nested->get('Deploy')->items))->toBe(['Production'])
        ->and($nested->get('Troubleshooting')->index)->toBeNull()
        ->and(array_map(fn ($i) => $i->title, $nested->get('Troubleshooting')->items))->toBe(['FAQ']);
});
