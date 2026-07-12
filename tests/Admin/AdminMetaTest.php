<?php

use STS\Docent\Runtime\IntegrationRegistry;

beforeEach(function () {
    $this->actingAs($this->adminUser());
});

it('returns registered integration metadata with labels for the pickers', function () {
    app(IntegrationRegistry::class)->condition(
        'advanced-exports',
        fn () => true,
        label: 'Advanced exports',
        description: 'Account has the advanced exports add-on.',
    );

    $this->getJson('/docs/admin/api/meta')
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'advanced-exports',
            'label' => 'Advanced exports',
            'description' => 'Account has the advanced exports add-on.',
        ]);
});

it('lists conditions, values, links, components, and audiences', function () {
    $this->getJson('/docs/admin/api/meta')
        ->assertOk()
        ->assertJsonStructure([
            'conditions', 'values', 'links', 'components', 'audiences', 'icons', 'abilities',
        ])
        // The base test suite registers these on every context.
        ->assertJsonFragment(['name' => 'account.plan'])
        ->assertJsonFragment(['name' => 'billing.settings']);
});

it('includes the built-in icons and registered abilities with humanized labels', function () {
    $meta = $this->getJson('/docs/admin/api/meta')->assertOk()->json();

    expect($meta['icons'])->toContain('rocket')
        ->and($meta['abilities'])->toContain(['name' => 'reports.view', 'label' => 'View reports'])
        ->and($meta['abilities'])->toContain(['name' => 'billing.manage', 'label' => 'Manage billing'])
        ->and($meta['abilities'])->toContain(['name' => 'viewDocentAdmin', 'label' => 'View docent admin']);
});
