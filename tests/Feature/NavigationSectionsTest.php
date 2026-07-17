<?php

use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationGroup;

it('composes ordered sections from top-level group metadata', function () {
    $sections = app(DocentManager::class)->navigationSections(
        $this->contextFor($this->adminUser()),
        'reports',
    );

    expect(array_map(fn ($section) => $section->label, $sections))
        ->toBe(['Documentation', 'Reports'])
        ->and($sections[0]->directory)->toBeNull()
        ->and($sections[0]->active)->toBeFalse()
        ->and($sections[1]->directory)->toBe('reports')
        ->and($sections[1]->active)->toBeTrue()
        ->and($sections[1]->url)->toBe(url('/docs/reports'));

    $defaultGroups = array_values(array_filter(
        $sections[0]->navigation,
        fn ($node) => $node instanceof NavigationGroup,
    ));

    expect(array_map(fn (NavigationGroup $group) => $group->label, $defaultGroups))
        ->toBe(['Guides', 'Billing']);
});

it('uses a configurable default section label and keeps it first', function () {
    config()->set('docent.sites.docs.navigation.default_section', 'Help center');

    $sections = app(DocentManager::class)->navigationSections($this->contextFor($this->adminUser()));

    expect(array_map(fn ($section) => $section->label, $sections))
        ->toBe(['Help center', 'Reports']);
});

it('collapses sections with no visible pages for the viewer', function () {
    $sections = app(DocentManager::class)->navigationSections($this->contextFor(null), 'reports');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]->label)->toBe('Documentation')
        ->and($sections[0]->active)->toBeTrue();
});

it('keeps previous and next navigation inside the active section', function () {
    $manager = app(DocentManager::class);
    $context = $this->contextFor($this->adminUser());

    [$reportsPrevious, $reportsNext] = $manager->prevNext('reports', $context);
    [$defaultPrevious, $defaultNext] = $manager->prevNext('billing/secret', $context);

    expect($reportsPrevious)->toBeNull()
        ->and($reportsNext)->toBeNull()
        ->and($defaultPrevious?->slug)->toBe('billing/overview')
        ->and($defaultNext)->toBeNull();
});

it('renders visible section switches without changing page urls', function () {
    $this->actingAs($this->adminUser())
        ->get('/docs/reports')
        ->assertOk()
        ->assertSee('aria-label="Documentation sections"', false)
        ->assertSee('href="http://localhost/docs/reports"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Sections')
        ->assertDontSee('href="http://localhost/docs/billing/secret" rel="next"', false);
});
